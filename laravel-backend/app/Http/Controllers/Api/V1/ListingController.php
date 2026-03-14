<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingBrowseRequest;
use App\Http\Requests\OwnerListingIndexRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    /**
     * @var list<string>
     */
    private const ITEM_CONDITIONS = [
        'new',
        'used',
    ];

    /**
     * @var list<string>
     */
    private const LISTING_STATUSES = [
        'pending_review',
        'available',
        'reserved',
        'sold',
        'rejected',
        'suspended',
        'archived',
    ];

    /**
     * @var list<string>
     */
    private const BROWSE_VISIBLE_STATUSES = [
        'available',
        'reserved',
        'sold',
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const BROWSE_SORT_MAP = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'price_asc' => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
        'title_asc' => ['title', 'asc'],
        'title_desc' => ['title', 'desc'],
    ];

    public function index(ListingBrowseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 10);
        $sortBy = (string) ($validated['sort_by'] ?? 'newest');
        $sortDirOverride = $validated['sort_dir'] ?? null;
        [$sortColumn, $sortDirection] = self::BROWSE_SORT_MAP[$sortBy];

        if (is_string($sortDirOverride) && in_array($sortDirOverride, ['asc', 'desc'], true)) {
            $sortDirection = $sortDirOverride;
        }

        $query = $this->visibleListingsQuery();

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';
            $locationColumns = $this->searchableLocationColumns();

            $query->where(function (Builder $builder) use ($searchPattern, $locationColumns): void {
                $builder
                    ->where('title', 'like', $searchPattern)
                    ->orWhere('description', 'like', $searchPattern);

                foreach ($locationColumns as $column) {
                    $builder->orWhere($column, 'like', $searchPattern);
                }
            });
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', (int) $validated['category_id']);
        }

        if (isset($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        if (isset($validated['condition'])) {
            $query->where('item_condition', (string) $validated['condition']);
        }

        if (isset($validated['status'])) {
            $query->where('listing_status', (string) $validated['status']);
        }

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', $sortDirection)
            ->paginate($perPage);

        return ApiResponse::success('Listings retrieved successfully.', [
            'listings' => array_map(
                fn (Listing $listing): array => $this->serializeListingDetail($listing),
                $paginator->items()
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Listing $listing): JsonResponse
    {
        $listing = $this->visibleListingsQuery()
            ->with([
                'category:id,name,slug',
                'listingImages' => static function ($query): void {
                    $query
                        ->select(['id', 'listing_id', 'image_path', 'sort_order', 'is_primary'])
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->whereKey($listing->id)
            ->firstOrFail();

        return ApiResponse::success('Listing retrieved successfully.', [
            'listing' => $this->serializeListingDetail($listing),
        ]);
    }

    public function mine(OwnerListingIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $userId = (int) $request->user()->id;

        $paginator = $this->ownerListingsQuery($userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Owner listings retrieved successfully.', [
            'listings' => array_map(
                fn (Listing $listing): array => $this->serializeOwnerListing($listing),
                $paginator->items()
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());

        $listing = Listing::query()->create([
            'user_id' => (int) $request->user()->id,
            'category_id' => $this->resolveCategoryId($validated),
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'price' => $validated['price'],
            'item_condition' => $validated['item_condition'] ?? null,
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
            'meetup_arrangement' => $validated['meetup_arrangement'] ?? null,
            'service_type' => $validated['service_type'] ?? null,
            'service_mode' => $validated['service_mode'] ?? null,
            'listing_status' => $this->resolveListingStatus($validated),
        ]);

        return ApiResponse::success('Listing created.', [
            'listing' => $this->serializeOwnerListing(
                $listing->fresh(['category:id,name,slug', 'approvedByUser:id,first_name,middle_name,last_name'])
            ),
        ], 201);
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $validated = $request->validate($this->validationRules());

        $listing->fill([
            'category_id' => $this->resolveCategoryId($validated),
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'price' => $validated['price'],
            'item_condition' => $validated['item_condition'] ?? null,
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
            'meetup_arrangement' => $validated['meetup_arrangement'] ?? null,
            'service_type' => $validated['service_type'] ?? null,
            'service_mode' => $validated['service_mode'] ?? null,
            'listing_status' => $this->resolveListingStatus($validated),
        ]);
        $listing->save();

        return ApiResponse::success('Listing updated.', [
            'listing' => $this->serializeOwnerListing(
                $listing->fresh(['category:id,name,slug', 'approvedByUser:id,first_name,middle_name,last_name'])
            ),
        ]);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        DB::transaction(function () use ($listing): void {
            $images = $listing->listingImages()->get(['id', 'listing_id', 'image_path']);

            foreach ($images as $image) {
                $image->delete();
            }

            $listing->delete();
        });

        return ApiResponse::success('Listing deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_without:category_slug'],
            'category_slug' => ['nullable', 'string', 'exists:categories,slug', 'required_without:category_id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'item_condition' => ['nullable', 'string', Rule::in(self::ITEM_CONDITIONS)],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_negotiable' => ['nullable', 'boolean'],
            'meetup_arrangement' => ['nullable', 'string', 'max:120'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'service_mode' => ['nullable', 'string', Rule::in(['onsite', 'remote', 'meetup'])],
            'listing_status' => ['nullable', 'string', Rule::in(self::LISTING_STATUSES)],
            'status' => ['nullable', 'string', Rule::in(self::LISTING_STATUSES)],
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveListingStatus(array $validated): string
    {
        $status = $validated['listing_status'] ?? $validated['status'] ?? null;

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return 'pending_review';
    }

    private function isListingOwner(Request $request, Listing $listing): bool
    {
        $userId = $request->user()?->id;

        return is_int($userId) && $userId === (int) $listing->user_id;
    }

    private function resolveCategoryId(array $validated): int
    {
        if (isset($validated['category_id'])) {
            return (int) $validated['category_id'];
        }

        $categorySlug = trim((string) ($validated['category_slug'] ?? ''));
        if ($categorySlug === '') {
            throw ValidationException::withMessages([
                'category_id' => ['The category field is required.'],
            ]);
        }

        $categoryId = Category::query()
            ->where('slug', $categorySlug)
            ->value('id');

        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'category_slug' => ['The selected category slug is invalid.'],
            ]);
        }

        return (int) $categoryId;
    }

    /**
     * @return list<string>
     */
    private function searchableLocationColumns(): array
    {
        return ['meetup_arrangement'];
    }

    private function visibleListingsQuery(): Builder
    {
        $query = Listing::query()
            ->with(['category:id,name,slug', 'listingImages'])
            ->whereIn('listing_status', self::BROWSE_VISIBLE_STATUSES)
            ->where('is_flagged', false);

        if (Schema::hasColumn('listings', 'approved_at')) {
            $query->whereNotNull('approved_at');
        }

        if (Schema::hasColumn('users', 'is_disabled')) {
            $query->whereHas('user', static function (Builder $builder): void {
                $builder->where('is_disabled', false);
            });
        }

        return $query;
    }

    private function ownerListingsQuery(int $userId): Builder
    {
        return Listing::query()
            ->with([
                'category:id,name,slug',
                'listingImages',
                'approvedByUser:id,first_name,middle_name,last_name',
            ])
            ->where('user_id', $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListingDetail(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'user_id' => (int) $listing->user_id,
            'category_id' => (int) $listing->category_id,
            'title' => $listing->title,
            'description' => $listing->description,
            'price' => $listing->price,
            'item_condition' => (string) $listing->item_condition,
            'listing_status' => (string) $listing->listing_status,
            'meetup_arrangement' => $listing->meetup_arrangement,
            'service_type' => $listing->service_type,
            'service_mode' => $listing->service_mode,
            'category' => $listing->category ? [
                'id' => $listing->category->id,
                'name' => $listing->category->name,
                'slug' => $listing->category->slug,
            ] : null,
            'images' => array_map(
                static fn (ListingImage $image): array => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'sort_order' => (int) $image->sort_order,
                    'is_primary' => (bool) $image->is_primary,
                ],
                $listing->listingImages->all()
            ),
            'created_at' => $listing->created_at?->toISOString(),
            'updated_at' => $listing->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOwnerListing(Listing $listing): array
    {
        $moderationStatus = $this->listingModerationStatus($listing);
        $adminNote = trim((string) $listing->moderation_note);
        $reviewedBy = $listing->relationLoaded('approvedByUser')
            ? $listing->approvedByUser
            : $listing->approvedByUser()->first();

        return [
            'id' => $listing->id,
            'user_id' => (int) $listing->user_id,
            'category_id' => (int) $listing->category_id,
            'title' => $listing->title,
            'description' => $listing->description,
            'price' => $listing->price,
            'item_condition' => (string) $listing->item_condition,
            'listing_status' => (string) $listing->listing_status,
            'item_status' => $this->listingItemStatus($listing),
            'moderation_status' => $moderationStatus,
            'moderation_label' => $this->listingModerationLabel($moderationStatus),
            'admin_note' => $moderationStatus === 'declined' && $adminNote !== '' ? $adminNote : null,
            'moderation_note' => $adminNote !== '' ? $adminNote : null,
            'meetup_arrangement' => $listing->meetup_arrangement,
            'service_type' => $listing->service_type,
            'service_mode' => $listing->service_mode,
            'category' => $listing->category ? [
                'id' => $listing->category->id,
                'name' => $listing->category->name,
                'slug' => $listing->category->slug,
            ] : null,
            'images' => array_map(
                static fn (ListingImage $image): array => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'sort_order' => (int) $image->sort_order,
                    'is_primary' => (bool) $image->is_primary,
                ],
                $listing->listingImages->all()
            ),
            'approved_by_user_id' => $listing->approved_by_user_id === null ? null : (int) $listing->approved_by_user_id,
            'reviewed_at' => $listing->approved_at?->toISOString(),
            'approved_at' => $listing->approved_at?->toISOString(),
            'reviewed_by' => $listing->approved_by_user_id === null ? null : (int) $listing->approved_by_user_id,
            'reviewed_by_name' => $reviewedBy?->fullName(),
            'created_at' => $listing->created_at?->toISOString(),
            'updated_at' => $listing->updated_at?->toISOString(),
        ];
    }

    private function listingModerationStatus(Listing $listing): string
    {
        if ((string) $listing->listing_status === 'rejected') {
            return 'declined';
        }

        if ($listing->approved_at !== null) {
            return 'approved';
        }

        return 'pending';
    }

    private function listingModerationLabel(string $moderationStatus): string
    {
        return match ($moderationStatus) {
            'approved' => 'Approved',
            'declined' => 'Declined',
            default => 'Under Review - wait for approval',
        };
    }

    private function listingItemStatus(Listing $listing): ?string
    {
        $listingStatus = (string) $listing->listing_status;

        if (in_array($listingStatus, ['available', 'reserved', 'sold'], true)) {
            return $listingStatus;
        }

        return null;
    }
}
