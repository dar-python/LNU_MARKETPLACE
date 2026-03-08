<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecideInquiryRequest;
use App\Http\Requests\InquiryIndexRequest;
use App\Http\Requests\ShowInquiryRequest;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
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

    private const RESERVED_LISTING_STATUS = 'reserved';

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
            'status' => Inquiry::STATUS_PENDING,
            'inquiry_status' => 'new',
        ]);

        return ApiResponse::success('Inquiry submitted.', [
            'inquiry' => $this->serializeInquiry(
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
                fn (Inquiry $inquiry): array => $this->serializeInquiry($inquiry),
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
                fn (Inquiry $inquiry): array => $this->serializeInquiry($inquiry),
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
            'inquiry' => $this->serializeInquiry(
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail()
            ),
        ]);
    }

    public function decide(DecideInquiryRequest $request, Inquiry $inquiry): JsonResponse
    {
        if (! $inquiry->isPending()) {
            return $this->pendingDecisionError();
        }

        $status = (string) $request->validated('status');
        $decidedAt = now();

        DB::transaction(function () use ($request, $inquiry, $status, $decidedAt): void {
            $lockedInquiry = Inquiry::query()
                ->lockForUpdate()
                ->findOrFail($inquiry->id);

            if (! $lockedInquiry->isPending()) {
                throw new HttpResponseException($this->pendingDecisionError());
            }

            $listing = Listing::query()
                ->lockForUpdate()
                ->findOrFail($lockedInquiry->listing_id);

            $listingStatusBeforeDecision = (string) $listing->listing_status;
            $listingStatusAfterDecision = $listingStatusBeforeDecision;

            $lockedInquiry->fill([
                'status' => $status,
                'decided_at' => $decidedAt,
                'decided_by' => (int) $request->user()->id,
                'inquiry_status' => $this->legacyInquiryStatus($status),
                'responded_at' => $decidedAt,
            ]);
            $lockedInquiry->save();

            if ($status === Inquiry::STATUS_ACCEPTED) {
                $listingStatusAfterDecision = self::RESERVED_LISTING_STATUS;

                if ($listing->listing_status !== self::RESERVED_LISTING_STATUS) {
                    $listing->forceFill([
                        'listing_status' => self::RESERVED_LISTING_STATUS,
                    ])->save();
                }
            }

            $this->logDecisionActivity(
                $request,
                $lockedInquiry,
                $status,
                $listingStatusBeforeDecision,
                $listingStatusAfterDecision
            );
        });

        return ApiResponse::success($this->decisionMessage($status), [
            'inquiry' => $this->serializeInquiry(
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

    private function decisionMessage(string $status): string
    {
        return $status === Inquiry::STATUS_ACCEPTED
            ? 'Inquiry accepted.'
            : 'Inquiry declined.';
    }

    private function pendingDecisionError(): JsonResponse
    {
        return ApiResponse::error('Only pending inquiries can be decided.', [
            'status' => ['The inquiry has already been decided.'],
        ], 422);
    }

    private function legacyInquiryStatus(string $status): string
    {
        return $status === Inquiry::STATUS_ACCEPTED ? 'resolved' : 'closed';
    }

    private function logDecisionActivity(
        DecideInquiryRequest $request,
        Inquiry $inquiry,
        string $status,
        string $listingStatusBeforeDecision,
        string $listingStatusAfterDecision
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => $status === Inquiry::STATUS_ACCEPTED
                ? 'inquiry.accepted'
                : 'inquiry.declined',
            'subject_type' => $inquiry->getMorphClass(),
            'subject_id' => $inquiry->id,
            'description' => $this->decisionMessage($status),
            'metadata' => [
                'inquiry_id' => $inquiry->id,
                'listing_id' => (int) $inquiry->listing_id,
                'inquiry_status_from' => Inquiry::STATUS_PENDING,
                'inquiry_status_to' => $status,
                'listing_status_from' => $listingStatusBeforeDecision,
                'listing_status_to' => $listingStatusAfterDecision,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInquiry(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'listing_id' => (int) $inquiry->listing_id,
            'sender_user_id' => (int) $inquiry->sender_user_id,
            'recipient_user_id' => (int) $inquiry->recipient_user_id,
            'subject' => $inquiry->subject,
            'message' => $inquiry->message,
            'preferred_contact_method' => $inquiry->preferred_contact_method,
            'status' => (string) $inquiry->status,
            'inquiry_status' => $inquiry->inquiry_status,
            'decided_at' => $inquiry->decided_at?->toJSON(),
            'decided_by' => $inquiry->decided_by === null ? null : (int) $inquiry->decided_by,
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
