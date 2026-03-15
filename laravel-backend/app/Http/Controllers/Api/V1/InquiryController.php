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
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    private const SOLD_LISTING_STATUS = 'sold';

    /**
     * @var array<string, bool>
     */
    private const PROFILE_PRIVACY_FIELDS = [
        'is_contact_public' => false,
        'is_program_public' => true,
        'is_year_level_public' => true,
        'is_organization_public' => true,
        'is_section_public' => true,
        'is_bio_public' => true,
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
            'status' => Inquiry::STATUS_PENDING,
            'inquiry_status' => 'new',
        ]);

        return ApiResponse::success('Inquiry submitted.', [
            'inquiry' => $this->serializeInquiry(
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail(),
                $userId
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
                fn (Inquiry $inquiry): array => $this->serializeInquiry($inquiry, $userId),
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
                fn (Inquiry $inquiry): array => $this->serializeInquiry($inquiry, $userId),
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
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail(),
                (int) $request->user()->id
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
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail(),
                (int) $request->user()->id
            ),
        ]);
    }

    public function sellerConfirm(Request $request, Inquiry $inquiry): JsonResponse
    {
        $userId = $request->user()?->id;

        if (! is_int($userId) || ! $inquiry->loadMissing('listing')->canBeDecidedBy($userId)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        if ((string) $inquiry->status !== Inquiry::STATUS_ACCEPTED) {
            return $this->acceptedConfirmationError();
        }

        if ($inquiry->seller_confirmed_at !== null) {
            return ApiResponse::error('Seller has already confirmed this transaction.', [
                'seller_confirmed_at' => ['The seller confirmation has already been recorded.'],
            ], 422);
        }

        $request->validate([
            'proof_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $proofImage = $request->file('proof_image');
        $storedPath = null;
        $previousProofImagePath = $this->nullableString($inquiry->proof_image_path);
        $sellerConfirmedAt = now();

        try {
            if (! $proofImage instanceof UploadedFile) {
                throw new \RuntimeException('The uploaded proof image is invalid.');
            }

            $storedPath = $proofImage->store('inquiries/'.$inquiry->id, 'public');

            $lockedInquiry = Inquiry::query()
                ->whereKey($inquiry->id)
                ->firstOrFail();

            if ((string) $lockedInquiry->status !== Inquiry::STATUS_ACCEPTED) {
                throw new \RuntimeException('Only accepted inquiries can proceed to confirmation.');
            }

            $lockedInquiry->fill([
                'proof_image_path' => $storedPath,
                'seller_confirmed_at' => $sellerConfirmedAt,
            ]);
            $lockedInquiry->save();
        } catch (\Throwable $throwable) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('public')->delete($storedPath);
            }

            return ApiResponse::error($throwable->getMessage(), null, 500);
        }

        if (
            is_string($storedPath)
            && $storedPath !== ''
            && is_string($previousProofImagePath)
            && $previousProofImagePath !== ''
            && $previousProofImagePath !== $storedPath
        ) {
            Storage::disk('public')->delete($previousProofImagePath);
        }

        return ApiResponse::success('Seller confirmation recorded.', [
            'inquiry' => $this->serializeInquiry(
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail(),
                $userId
            ),
        ]);
    }

    public function buyerConfirm(Request $request, Inquiry $inquiry): JsonResponse
    {
        $userId = $request->user()?->id;

        if (! is_int($userId) || $userId !== (int) $inquiry->sender_user_id) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        if ((string) $inquiry->status !== Inquiry::STATUS_ACCEPTED) {
            return $this->acceptedConfirmationError();
        }

        if ($inquiry->seller_confirmed_at === null) {
            return ApiResponse::error('Seller confirmation is required before buyer confirmation.', [
                'seller_confirmed_at' => ['The seller must confirm the transaction first.'],
            ], 422);
        }

        if ($inquiry->buyer_confirmed_at !== null) {
            return ApiResponse::error('Buyer has already confirmed receipt.', [
                'buyer_confirmed_at' => ['The buyer confirmation has already been recorded.'],
            ], 422);
        }

        DB::transaction(function () use ($request, $inquiry, $userId): void {
            $lockedInquiry = Inquiry::query()
                ->lockForUpdate()
                ->findOrFail($inquiry->id);

            if ((int) $lockedInquiry->sender_user_id !== $userId) {
                throw new HttpResponseException(ApiResponse::error('Forbidden.', null, 403));
            }

            if ((string) $lockedInquiry->status !== Inquiry::STATUS_ACCEPTED) {
                throw new HttpResponseException($this->acceptedConfirmationError());
            }

            if ($lockedInquiry->seller_confirmed_at === null) {
                throw ValidationException::withMessages([
                    'seller_confirmed_at' => ['The seller must confirm the transaction first.'],
                ]);
            }

            if ($lockedInquiry->buyer_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'buyer_confirmed_at' => ['The buyer confirmation has already been recorded.'],
                ]);
            }

            $listing = Listing::query()
                ->lockForUpdate()
                ->findOrFail($lockedInquiry->listing_id);

            $completedAt = now();
            $listingStatusBeforeCompletion = (string) $listing->listing_status;
            $listingStatusAfterCompletion = self::SOLD_LISTING_STATUS;

            $lockedInquiry->fill([
                'buyer_confirmed_at' => $completedAt,
                'status' => Inquiry::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                'inquiry_status' => $this->legacyInquiryStatus(Inquiry::STATUS_COMPLETED),
                'responded_at' => $completedAt,
            ]);
            $lockedInquiry->save();

            if (
                $listing->listing_status !== self::SOLD_LISTING_STATUS
                || $listing->sold_at === null
            ) {
                $listing->forceFill([
                    'listing_status' => self::SOLD_LISTING_STATUS,
                    'sold_at' => $completedAt,
                ])->save();
            }

            $this->logCompletionActivity(
                $request,
                $lockedInquiry,
                $listingStatusBeforeCompletion,
                $listingStatusAfterCompletion
            );
        });

        return ApiResponse::success('Buyer confirmation recorded.', [
            'inquiry' => $this->serializeInquiry(
                $this->inquiryQuery()->whereKey($inquiry->id)->firstOrFail(),
                $userId
            ),
        ]);
    }

    private function inquiryQuery(): Builder
    {
        return Inquiry::query()->with([
            'listing:id,user_id,title,listing_status',
            'sender:id,first_name,middle_name,last_name,contact_number,program,year_level,organization,section,bio,is_contact_public,is_program_public,is_year_level_public,is_organization_public,is_section_public,is_bio_public',
            'recipient:id,first_name,middle_name,last_name,contact_number,program,year_level,organization,section,bio,is_contact_public,is_program_public,is_year_level_public,is_organization_public,is_section_public,is_bio_public',
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

    private function acceptedConfirmationError(): JsonResponse
    {
        return ApiResponse::error('Only accepted inquiries can proceed to confirmation.', [
            'status' => ['The inquiry must be accepted before it can proceed to confirmation.'],
        ], 422);
    }

    private function legacyInquiryStatus(string $status): string
    {
        return match ($status) {
            Inquiry::STATUS_ACCEPTED => 'resolved',
            Inquiry::STATUS_COMPLETED => 'completed',
            default => 'closed',
        };
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

    private function logCompletionActivity(
        Request $request,
        Inquiry $inquiry,
        string $listingStatusBeforeCompletion,
        string $listingStatusAfterCompletion
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => 'inquiry.completed',
            'subject_type' => $inquiry->getMorphClass(),
            'subject_id' => $inquiry->id,
            'description' => 'Inquiry transaction completed.',
            'metadata' => [
                'inquiry_id' => $inquiry->id,
                'listing_id' => (int) $inquiry->listing_id,
                'inquiry_status_from' => Inquiry::STATUS_ACCEPTED,
                'inquiry_status_to' => Inquiry::STATUS_COMPLETED,
                'listing_status_from' => $listingStatusBeforeCompletion,
                'listing_status_to' => $listingStatusAfterCompletion,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInquiry(Inquiry $inquiry, int $viewerUserId): array
    {
        $counterparty = $this->counterpartyForViewer($inquiry, $viewerUserId);
        $counterpartyPayload = $this->transformCounterparty($counterparty);
        $counterpartyFields = $counterpartyPayload === null
            ? []
            : array_filter([
                'counterparty_contact' => $counterpartyPayload['contact_number'] ?? null,
                'counterparty_program' => $counterpartyPayload['program'] ?? null,
                'counterparty_year_level' => $counterpartyPayload['year_level'] ?? null,
                'counterparty_organization' => $counterpartyPayload['organization'] ?? null,
                'counterparty_section' => $counterpartyPayload['section'] ?? null,
                'counterparty_bio' => $counterpartyPayload['bio'] ?? null,
            ], static fn (mixed $value): bool => $value !== null);

        return array_merge([
            'id' => $inquiry->id,
            'listing_id' => (int) $inquiry->listing_id,
            'sender_user_id' => (int) $inquiry->sender_user_id,
            'recipient_user_id' => (int) $inquiry->recipient_user_id,
            'subject' => $inquiry->subject,
            'message' => $inquiry->message,
            'preferred_contact_method' => $inquiry->preferred_contact_method,
            'status' => (string) $inquiry->status,
            'inquiry_status' => $inquiry->inquiry_status,
            'proof_image_path' => $this->nullableString($inquiry->proof_image_path),
            'completed_at' => $inquiry->completed_at?->toJSON(),
            'seller_confirmed_at' => $inquiry->seller_confirmed_at?->toJSON(),
            'buyer_confirmed_at' => $inquiry->buyer_confirmed_at?->toJSON(),
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
            'counterparty' => $counterpartyPayload,
        ], $counterpartyFields);
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

    /**
     * @return array<string, mixed>|null
     */
    private function transformCounterparty(?User $user): ?array
    {
        $payload = $this->transformUser($user);

        if ($payload === null || ! $user) {
            return $payload;
        }

        return array_merge($payload, array_filter([
            'contact_number' => $this->publicValue(
                $this->profilePrivacyValue($user, 'is_contact_public'),
                $user->contact_number
            ),
            'program' => $this->publicValue(
                $this->profilePrivacyValue($user, 'is_program_public'),
                $user->program
            ),
            'year_level' => $this->publicValue(
                $this->profilePrivacyValue($user, 'is_year_level_public'),
                $user->year_level
            ),
            'organization' => $this->publicValue(
                $this->profilePrivacyValue($user, 'is_organization_public'),
                $user->organization
            ),
            'section' => $this->publicValue(
                $this->profilePrivacyValue($user, 'is_section_public'),
                $user->section
            ),
            'bio' => $this->publicValue(
                $this->profilePrivacyValue($user, 'is_bio_public'),
                $user->bio
            ),
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function counterpartyForViewer(Inquiry $inquiry, int $viewerUserId): ?User
    {
        if ($viewerUserId === (int) $inquiry->sender_user_id) {
            return $inquiry->recipient;
        }

        if ($viewerUserId === (int) $inquiry->recipient_user_id) {
            return $inquiry->sender;
        }

        $listingOwnerId = (int) ($inquiry->listing?->user_id ?? 0);

        return $viewerUserId === $listingOwnerId
            ? $inquiry->sender
            : null;
    }

    private function publicValue(bool $isPublic, mixed $value): ?string
    {
        if (! $isPublic || ! is_string($value)) {
            return null;
        }

        return $this->nullableString($value);
    }

    private function profilePrivacyValue(User $user, string $field): bool
    {
        $fallback = self::PROFILE_PRIVACY_FIELDS[$field] ?? true;

        if (! Schema::hasColumn('users', $field)) {
            return $fallback;
        }

        return (bool) $user->{$field};
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
