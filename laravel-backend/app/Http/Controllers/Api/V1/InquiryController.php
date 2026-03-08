<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InquiryIndexRequest;
use App\Http\Requests\ShowInquiryRequest;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InquiryController extends Controller
{
    /**
     * @var list<string>
     */
    private const INQUIRABLE_STATUSES = [
        'available',
        'reserved',
        'sold',
    ];

    public function store(StoreInquiryRequest $request, Listing $listing): JsonResponse
    {
        $userId = (int) $request->user()->id;

        if ($userId === (int) $listing->user_id) {
            throw ValidationException::withMessages([
                'listing' => ['You cannot send an inquiry for your own listing.'],
            ]);
        }

        $inquirableListing = $this->findInquirableListing($listing->id);

        if (! $inquirableListing) {
            throw ValidationException::withMessages([
                'listing' => ['The selected listing is not available for inquiries.'],
            ]);
        }

        $inquiry = Inquiry::query()->create([
            'listing_id' => $inquirableListing->id,
            'sender_user_id' => $userId,
            'recipient_user_id' => (int) $inquirableListing->user_id,
            'message' => (string) $request->validated('message'),
            'preferred_contact_method' => (string) $request->validated('preferred_contact_method'),
            'inquiry_status' => 'new',
        ]);

        return ApiResponse::success('Inquiry submitted.', [
            'inquiry' => $this->transformInquiry(
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail()
            ),
        ], 201);
    }

    public function sent(InquiryIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $userId = (int) $request->user()->id;
        $paginator = $this->inquiryQuery()
            ->where('sender_user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Sent inquiries retrieved successfully.', [
            'inquiries' => array_map(
                fn (Inquiry $inquiry): array => $this->transformInquiry($inquiry),
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

    public function received(InquiryIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $userId = (int) $request->user()->id;
        $paginator = $this->inquiryQuery()
            ->where('recipient_user_id', $userId)
            ->whereHas('listing', static function (Builder $builder) use ($userId): void {
                $builder->where('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Received inquiries retrieved successfully.', [
            'inquiries' => array_map(
                fn (Inquiry $inquiry): array => $this->transformInquiry($inquiry),
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

    public function show(ShowInquiryRequest $request, Inquiry $inquiry): JsonResponse
    {
        return ApiResponse::success('Inquiry retrieved successfully.', [
            'inquiry' => $this->transformInquiry(
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail()
            ),
        ]);
    }

    private function inquiryQuery(): Builder
    {
        return Inquiry::query()->with([
            'listing:id,user_id,title,listing_status',
            'sender:id,first_name,middle_name,last_name',
            'recipient:id,first_name,middle_name,last_name',
        ]);
    }

    private function findInquirableListing(int $listingId): ?Listing
    {
        return $this->applyInquirableConstraints(
            Listing::query()->whereKey($listingId)
        )->first();
    }

    private function applyInquirableConstraints(Builder $query): Builder
    {
        $query
            ->whereIn('listing_status', self::INQUIRABLE_STATUSES)
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
    private function transformInquiry(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'listing_id' => $inquiry->listing_id,
            'sender_user_id' => $inquiry->sender_user_id,
            'recipient_user_id' => $inquiry->recipient_user_id,
            'message' => $inquiry->message,
            'preferred_contact_method' => $inquiry->preferred_contact_method,
            'inquiry_status' => $inquiry->inquiry_status,
            'created_at' => $inquiry->created_at?->toISOString(),
            'updated_at' => $inquiry->updated_at?->toISOString(),
            'listing' => $inquiry->listing ? [
                'id' => $inquiry->listing->id,
                'user_id' => $inquiry->listing->user_id,
                'title' => $inquiry->listing->title,
                'listing_status' => $inquiry->listing->listing_status,
            ] : null,
            'sender' => $this->transformUser($inquiry->sender),
            'recipient' => $this->transformUser($inquiry->recipient),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function transformUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'full_name' => $user->fullName(),
        ];
    }
}
