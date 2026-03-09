<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingBrowseRequest;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    /**
     * @var list<string>
     */
    private const ITEM_CONDITIONS = [
        'new',
        'like_new',
        'good',
        'fair',
        'poor',
        'brandnew',
        'preowned',
    ];

    /**
     * @var array<string, string>
     */
    private const ITEM_CONDITION_NORMALIZATION_MAP = [
        'new' => 'brandnew',
        'like_new' => 'preowned',
        'good' => 'preowned',
        'fair' => 'preowned',
        'poor' => 'preowned',
        'brandnew' => 'brandnew',
        'preowned' => 'preowned',
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
            'listings' => $paginator->items(),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());

        $listing = Listing::query()->create([
            'user_id' => (int) $request->user()->id,
            'category_id' => (int) $validated['category_id'],
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'price' => $validated['price'],
            'item_condition' => $this->normalizeItemCondition((string) $validated['item_condition']),
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
            'campus_location' => $validated['campus_location'] ?? null,
            'listing_status' => $this->resolveListingStatus($validated),
        ]);

        return ApiResponse::success('Listing created.', [
            'listing' => $listing->fresh(),
        ], 201);
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $validated = $request->validate($this->validationRules());

        $listing->fill([
            'category_id' => (int) $validated['category_id'],
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'price' => $validated['price'],
            'item_condition' => $this->normalizeItemCondition((string) $validated['item_condition']),
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
            'campus_location' => $validated['campus_location'] ?? null,
            'listing_status' => $this->resolveListingStatus($validated),
        ]);
        $listing->save();

        return ApiResponse::success('Listing updated.', [
            'listing' => $listing->fresh(),
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'item_condition' => ['required', 'string', Rule::in(self::ITEM_CONDITIONS)],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_negotiable' => ['nullable', 'boolean'],
            'campus_location' => ['nullable', 'string', 'max:120'],
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

    private function normalizeItemCondition(string $itemCondition): string
    {
        return self::ITEM_CONDITION_NORMALIZATION_MAP[$itemCondition] ?? 'preowned';
    }

    /**
     * @return list<string>
     */
    private function searchableLocationColumns(): array
    {
        $columns = ['campus_location'];

        if (Schema::hasColumn('listings', 'meetup_location')) {
            $columns[] = 'meetup_location';
        }

        return $columns;
    }

    private function visibleListingsQuery(): Builder
    {
        $query = Listing::query()
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
            'campus_location' => $listing->campus_location,
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
}
