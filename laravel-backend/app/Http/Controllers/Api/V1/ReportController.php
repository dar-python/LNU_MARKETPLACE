<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateReportActionRequest;
use App\Http\Requests\ReportIndexRequest;
use App\Http\Requests\ShowReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportStatusRequest;
use App\Models\ActivityLog;
use App\Models\Listing;
use App\Models\PostReport;
use App\Models\PostReportStatusHistory;
use App\Models\User;
use App\Models\UserReport;
use App\Models\UserReportStatusHistory;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /**
     * @var list<string>
     */
    private const REPORTABLE_LISTING_STATUSES = [
        'available',
        'reserved',
        'sold',
    ];

    public function storeListing(StoreReportRequest $request, Listing $listing): JsonResponse
    {
        $userId = (int) $request->user()->id;

        if ($userId === (int) $listing->user_id) {
            throw ValidationException::withMessages([
                'listing' => ['You cannot report your own listing.'],
            ]);
        }

        $reportableListing = $this->findReportableListing($listing->id);

        if (! $reportableListing) {
            throw ValidationException::withMessages([
                'listing' => ['The selected listing is not available for reporting.'],
            ]);
        }

        $report = $this->createListingReport($request, $reportableListing);

        return ApiResponse::success('Report submitted.', [
            'report' => $this->serializePostReport(
                $this->postReportQuery()->whereKey($report->id)->firstOrFail()
            ),
        ], 201);
    }

    public function storeUser(StoreReportRequest $request, User $user): JsonResponse
    {
        $reporterId = (int) $request->user()->id;

        if ($reporterId === (int) $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot report your own account.'],
            ]);
        }

        $report = $this->createUserReport($request, $user);

        return ApiResponse::success('Report submitted.', [
            'report' => $this->serializeUserReport(
                $this->userReportQuery()->whereKey($report->id)->firstOrFail()
            ),
        ], 201);
    }

    public function mineListings(ReportIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $reporterId = (int) $request->user()->id;

        $paginator = $this->postReportQuery()
            ->where('reporter_user_id', $reporterId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Reports retrieved successfully.', [
            'reports' => array_map(
                fn (PostReport $report): array => $this->serializePostReport($report),
                $paginator->items()
            ),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function mineUsers(ReportIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $reporterId = (int) $request->user()->id;

        $paginator = $this->userReportQuery()
            ->where('reporter_user_id', $reporterId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Reports retrieved successfully.', [
            'reports' => array_map(
                fn (UserReport $report): array => $this->serializeUserReport($report),
                $paginator->items()
            ),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function showListing(ShowReportRequest $request, PostReport $postReport): JsonResponse
    {
        return ApiResponse::success('Report retrieved successfully.', [
            'report' => $this->serializePostReport(
                $this->postReportQuery()->whereKey($postReport->id)->firstOrFail()
            ),
        ]);
    }

    public function showUser(ShowReportRequest $request, UserReport $userReport): JsonResponse
    {
        return ApiResponse::success('Report retrieved successfully.', [
            'report' => $this->serializeUserReport(
                $this->userReportQuery()->whereKey($userReport->id)->firstOrFail()
            ),
        ]);
    }

    public function listingHistory(Request $request, PostReport $postReport): JsonResponse
    {
        return ApiResponse::success('Report history retrieved successfully.', [
            'history' => $postReport->statusHistories()
                ->with('changedBy:id,first_name,middle_name,last_name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (PostReportStatusHistory $history): array => $this->serializePostReportStatusHistory($history))
                ->all(),
        ]);
    }

    public function userHistory(Request $request, UserReport $userReport): JsonResponse
    {
        return ApiResponse::success('Report history retrieved successfully.', [
            'history' => $userReport->statusHistories()
                ->with('changedBy:id,first_name,middle_name,last_name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (UserReportStatusHistory $history): array => $this->serializeUserReportStatusHistory($history))
                ->all(),
        ]);
    }

    public function updateListingStatus(UpdateReportStatusRequest $request, PostReport $postReport): JsonResponse
    {
        [$report, $history] = $this->changeListingReportStatus($request, $postReport);

        return ApiResponse::success('Report status updated.', [
            'report' => $this->serializePostReport(
                $this->postReportQuery()->whereKey($report->id)->firstOrFail()
            ),
            'history' => $this->serializePostReportStatusHistory($history),
        ]);
    }

    public function updateUserStatus(UpdateReportStatusRequest $request, UserReport $userReport): JsonResponse
    {
        [$report, $history] = $this->changeUserReportStatus($request, $userReport);

        return ApiResponse::success('Report status updated.', [
            'report' => $this->serializeUserReport(
                $this->userReportQuery()->whereKey($report->id)->firstOrFail()
            ),
            'history' => $this->serializeUserReportStatusHistory($history),
        ]);
    }

    public function disableListing(ModerateReportActionRequest $request, PostReport $postReport): JsonResponse
    {
        [$report, $listing, $history] = $this->disableReportedListing($request, $postReport);

        return ApiResponse::success('Listing disabled.', [
            'report' => $this->serializePostReport(
                $this->postReportQuery()->whereKey($report->id)->firstOrFail()
            ),
            'listing' => $this->serializeListingModeration(
                Listing::query()->whereKey($listing->id)->firstOrFail()
            ),
            'history' => $history ? $this->serializePostReportStatusHistory($history) : null,
        ]);
    }

    public function suspendUser(ModerateReportActionRequest $request, UserReport $userReport): JsonResponse
    {
        [$report, $user, $history] = $this->suspendReportedUser($request, $userReport);

        return ApiResponse::success('User suspended.', [
            'report' => $this->serializeUserReport(
                $this->userReportQuery()->whereKey($report->id)->firstOrFail()
            ),
            'user' => $this->serializeModeratedUser(
                User::query()->whereKey($user->id)->firstOrFail()
            ),
            'history' => $history ? $this->serializeUserReportStatusHistory($history) : null,
        ]);
    }

    private function createListingReport(StoreReportRequest $request, Listing $listing): PostReport
    {
        $validated = $request->validated();
        $evidence = $request->file('evidence');
        $storedPath = null;
        $report = null;

        try {
            DB::transaction(function () use ($request, $validated, $listing, $evidence, &$storedPath, &$report): void {
                $report = PostReport::query()->create([
                    'listing_id' => $listing->id,
                    'reporter_user_id' => (int) $request->user()->id,
                    'reason_category' => (string) $validated['reason_category'],
                    'description' => (string) $validated['description'],
                    'status' => PostReport::STATUS_SUBMITTED,
                ]);

                $this->recordPostReportHistory($report, PostReport::STATUS_SUBMITTED);

                if ($evidence instanceof UploadedFile) {
                    $storedPath = $evidence->store('post-reports/'.$report->id, 'public');

                    $report->forceFill([
                        'evidence_path' => $storedPath,
                    ])->save();
                }

                $this->logSubmissionActivity($request, $report, 'listing', $storedPath !== null);
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredEvidencePath($storedPath);

            throw $exception;
        }

        if (! $report instanceof PostReport) {
            throw new \RuntimeException('Unable to create listing report.');
        }

        return $report;
    }

    private function createUserReport(StoreReportRequest $request, User $user): UserReport
    {
        $validated = $request->validated();
        $evidence = $request->file('evidence');
        $storedPath = null;
        $report = null;

        try {
            DB::transaction(function () use ($request, $validated, $user, $evidence, &$storedPath, &$report): void {
                $report = UserReport::query()->create([
                    'reported_user_id' => $user->id,
                    'reporter_user_id' => (int) $request->user()->id,
                    'reason_category' => (string) $validated['reason_category'],
                    'description' => (string) $validated['description'],
                    'status' => UserReport::STATUS_SUBMITTED,
                ]);

                $this->recordUserReportHistory($report, UserReport::STATUS_SUBMITTED);

                if ($evidence instanceof UploadedFile) {
                    $storedPath = $evidence->store('user-reports/'.$report->id, 'public');

                    $report->forceFill([
                        'evidence_path' => $storedPath,
                    ])->save();
                }

                $this->logSubmissionActivity($request, $report, 'user', $storedPath !== null);
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredEvidencePath($storedPath);

            throw $exception;
        }

        if (! $report instanceof UserReport) {
            throw new \RuntimeException('Unable to create user report.');
        }

        return $report;
    }

    private function postReportQuery(): Builder
    {
        return PostReport::query()->with([
            'listing:id,user_id,title,listing_status',
        ]);
    }

    private function userReportQuery(): Builder
    {
        return UserReport::query()->with([
            'reportedUser:id,first_name,middle_name,last_name',
        ]);
    }

    private function findReportableListing(int $listingId): ?Listing
    {
        return $this->applyReportableListingConstraints(
            Listing::query()->whereKey($listingId)
        )->first();
    }

    private function applyReportableListingConstraints(Builder $query): Builder
    {
        $query
            ->whereIn('listing_status', self::REPORTABLE_LISTING_STATUSES)
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
     * @return array{0: PostReport, 1: PostReportStatusHistory}
     */
    private function changeListingReportStatus(UpdateReportStatusRequest $request, PostReport $postReport): array
    {
        $validated = $request->validated();
        $status = (string) $validated['status'];
        $adminNote = $validated['admin_note'] ?? null;

        $report = null;
        $history = null;

        DB::transaction(function () use ($request, $postReport, $status, $adminNote, &$report, &$history): void {
            $report = PostReport::query()
                ->whereKey($postReport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = (string) $report->status;

            if ($previousStatus === $status) {
                throw ValidationException::withMessages([
                    'status' => ['The report already has this status.'],
                ]);
            }

            $report->forceFill([
                'status' => $status,
            ])->save();

            $history = $this->recordPostReportHistory(
                $report,
                $status,
                is_string($adminNote) ? $adminNote : null,
                $request->user()?->id
            );

            $this->logStatusChangeActivity(
                $request,
                $report,
                'listing',
                $previousStatus,
                $status,
                is_string($adminNote) ? $adminNote : null
            );
        });

        if (! $report instanceof PostReport || ! $history instanceof PostReportStatusHistory) {
            throw new \RuntimeException('Unable to update listing report status.');
        }

        return [$report, $history->loadMissing('changedBy:id,first_name,middle_name,last_name')];
    }

    /**
     * @return array{0: UserReport, 1: UserReportStatusHistory}
     */
    private function changeUserReportStatus(UpdateReportStatusRequest $request, UserReport $userReport): array
    {
        $validated = $request->validated();
        $status = (string) $validated['status'];
        $adminNote = $validated['admin_note'] ?? null;

        $report = null;
        $history = null;

        DB::transaction(function () use ($request, $userReport, $status, $adminNote, &$report, &$history): void {
            $report = UserReport::query()
                ->whereKey($userReport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = (string) $report->status;

            if ($previousStatus === $status) {
                throw ValidationException::withMessages([
                    'status' => ['The report already has this status.'],
                ]);
            }

            $report->forceFill([
                'status' => $status,
            ])->save();

            $history = $this->recordUserReportHistory(
                $report,
                $status,
                is_string($adminNote) ? $adminNote : null,
                $request->user()?->id
            );

            $this->logStatusChangeActivity(
                $request,
                $report,
                'user',
                $previousStatus,
                $status,
                is_string($adminNote) ? $adminNote : null
            );
        });

        if (! $report instanceof UserReport || ! $history instanceof UserReportStatusHistory) {
            throw new \RuntimeException('Unable to update user report status.');
        }

        return [$report, $history->loadMissing('changedBy:id,first_name,middle_name,last_name')];
    }

    /**
     * @return array{0: PostReport, 1: Listing, 2: PostReportStatusHistory|null}
     */
    private function disableReportedListing(ModerateReportActionRequest $request, PostReport $postReport): array
    {
        $adminNote = $request->validated('admin_note');
        $report = null;
        $listing = null;
        $history = null;

        DB::transaction(function () use ($request, $postReport, $adminNote, &$report, &$listing, &$history): void {
            $report = PostReport::query()
                ->whereKey($postReport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $listing = Listing::query()
                ->whereKey($report->listing_id)
                ->lockForUpdate()
                ->firstOrFail();

            $listingStatusBefore = (string) $listing->listing_status;
            $reportStatusBefore = (string) $report->status;

            if ($listingStatusBefore === 'suspended') {
                throw ValidationException::withMessages([
                    'listing' => ['The listing is already disabled.'],
                ]);
            }

            $attributes = [
                'listing_status' => 'suspended',
            ];

            if (is_string($adminNote)) {
                $attributes['moderation_note'] = $adminNote;
            }

            $listing->forceFill($attributes)->save();

            $history = $this->resolvePostReportAfterAction(
                $report,
                is_string($adminNote) ? $adminNote : null,
                $request->user()?->id
            );

            $this->logListingDisabledActivity(
                $request,
                $listing,
                $report,
                $listingStatusBefore,
                (string) $listing->listing_status,
                $reportStatusBefore,
                (string) $report->status,
                is_string($adminNote) ? $adminNote : null
            );
        });

        if (! $report instanceof PostReport || ! $listing instanceof Listing) {
            throw new \RuntimeException('Unable to disable listing from report context.');
        }

        return [
            $report,
            $listing,
            $history?->loadMissing('changedBy:id,first_name,middle_name,last_name'),
        ];
    }

    /**
     * @return array{0: UserReport, 1: User, 2: UserReportStatusHistory|null}
     */
    private function suspendReportedUser(ModerateReportActionRequest $request, UserReport $userReport): array
    {
        $adminNote = $request->validated('admin_note');
        $report = null;
        $user = null;
        $history = null;

        DB::transaction(function () use ($request, $userReport, $adminNote, &$report, &$user, &$history): void {
            $report = UserReport::query()
                ->whereKey($userReport->id)
                ->lockForUpdate()
                ->firstOrFail();

            $user = User::query()
                ->whereKey($report->reported_user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $user->loadMissing('roles:id,code');

            if ($this->isAdminUser($user)) {
                throw ValidationException::withMessages([
                    'user' => ['Admin accounts cannot be suspended from report moderation.'],
                ]);
            }

            $accountStatusBefore = $this->userAccountStatus($user);
            $isDisabledBefore = Schema::hasColumn('users', 'is_disabled')
                ? (bool) $user->is_disabled
                : true;
            $reportStatusBefore = (string) $report->status;

            if ($accountStatusBefore === 'suspended' && $isDisabledBefore) {
                throw ValidationException::withMessages([
                    'user' => ['The user is already suspended.'],
                ]);
            }

            $attributes = [
                $this->userAccountStatusColumn() => 'suspended',
            ];

            if (Schema::hasColumn('users', 'is_disabled')) {
                $attributes['is_disabled'] = true;
            }

            if (Schema::hasColumn('users', 'disabled_at')) {
                $attributes['disabled_at'] = now();
            }

            if (Schema::hasColumn('users', 'suspended_until')) {
                $attributes['suspended_until'] = null;
            }

            if (Schema::hasColumn('users', 'suspended_reason') && is_string($adminNote)) {
                $attributes['suspended_reason'] = $adminNote;
            }

            $user->forceFill($attributes)->save();

            $history = $this->resolveUserReportAfterAction(
                $report,
                is_string($adminNote) ? $adminNote : null,
                $request->user()?->id
            );

            $this->logUserSuspendedActivity(
                $request,
                $user,
                $report,
                $accountStatusBefore,
                $this->userAccountStatus($user),
                $isDisabledBefore,
                Schema::hasColumn('users', 'is_disabled') ? (bool) $user->is_disabled : true,
                $reportStatusBefore,
                (string) $report->status,
                is_string($adminNote) ? $adminNote : null
            );
        });

        if (! $report instanceof UserReport || ! $user instanceof User) {
            throw new \RuntimeException('Unable to suspend user from report context.');
        }

        return [
            $report,
            $user,
            $history?->loadMissing('changedBy:id,first_name,middle_name,last_name'),
        ];
    }

    private function recordPostReportHistory(
        PostReport $report,
        string $status,
        ?string $adminNote = null,
        ?int $changedBy = null
    ): PostReportStatusHistory {
        return $report->statusHistories()->create([
            'status' => $status,
            'admin_note' => $adminNote,
            'changed_by' => $changedBy,
        ]);
    }

    private function recordUserReportHistory(
        UserReport $report,
        string $status,
        ?string $adminNote = null,
        ?int $changedBy = null
    ): UserReportStatusHistory {
        return $report->statusHistories()->create([
            'status' => $status,
            'admin_note' => $adminNote,
            'changed_by' => $changedBy,
        ]);
    }

    private function logSubmissionActivity(
        StoreReportRequest $request,
        PostReport|UserReport $report,
        string $reportType,
        bool $hasEvidence
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => 'report.submitted',
            'subject_type' => $report->getMorphClass(),
            'subject_id' => $report->id,
            'description' => 'Report submitted.',
            'metadata' => [
                'report_id' => $report->id,
                'report_type' => $reportType,
                'listing_id' => $report instanceof PostReport ? (int) $report->listing_id : null,
                'reported_user_id' => $report instanceof UserReport ? (int) $report->reported_user_id : null,
                'reason_category' => (string) $report->reason_category,
                'has_evidence' => $hasEvidence,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    private function logStatusChangeActivity(
        Request $request,
        PostReport|UserReport $report,
        string $reportType,
        string $previousStatus,
        string $status,
        ?string $adminNote
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => 'report.status_changed',
            'subject_type' => $report->getMorphClass(),
            'subject_id' => $report->id,
            'description' => 'Report status updated.',
            'metadata' => [
                'report_id' => $report->id,
                'report_type' => $reportType,
                'listing_id' => $report instanceof PostReport ? (int) $report->listing_id : null,
                'reported_user_id' => $report instanceof UserReport ? (int) $report->reported_user_id : null,
                'status_from' => $previousStatus,
                'status_to' => $status,
                'admin_note' => $adminNote,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    private function resolvePostReportAfterAction(
        PostReport $report,
        ?string $adminNote,
        ?int $changedBy
    ): ?PostReportStatusHistory {
        if ((string) $report->status === PostReport::STATUS_RESOLVED) {
            return null;
        }

        $report->forceFill([
            'status' => PostReport::STATUS_RESOLVED,
        ])->save();

        return $this->recordPostReportHistory(
            $report,
            PostReport::STATUS_RESOLVED,
            $adminNote,
            $changedBy
        );
    }

    private function resolveUserReportAfterAction(
        UserReport $report,
        ?string $adminNote,
        ?int $changedBy
    ): ?UserReportStatusHistory {
        if ((string) $report->status === UserReport::STATUS_RESOLVED) {
            return null;
        }

        $report->forceFill([
            'status' => UserReport::STATUS_RESOLVED,
        ])->save();

        return $this->recordUserReportHistory(
            $report,
            UserReport::STATUS_RESOLVED,
            $adminNote,
            $changedBy
        );
    }

    private function logListingDisabledActivity(
        Request $request,
        Listing $listing,
        PostReport $report,
        string $listingStatusBefore,
        string $listingStatusAfter,
        string $reportStatusBefore,
        string $reportStatusAfter,
        ?string $adminNote
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => 'listing.disabled',
            'subject_type' => $listing->getMorphClass(),
            'subject_id' => $listing->id,
            'description' => 'Listing disabled.',
            'metadata' => [
                'report_id' => $report->id,
                'listing_id' => $listing->id,
                'listing_status_from' => $listingStatusBefore,
                'listing_status_to' => $listingStatusAfter,
                'report_status_from' => $reportStatusBefore,
                'report_status_to' => $reportStatusAfter,
                'admin_note' => $adminNote,
                'moderation_action' => 'disable_listing',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    private function logUserSuspendedActivity(
        Request $request,
        User $user,
        UserReport $report,
        string $accountStatusBefore,
        string $accountStatusAfter,
        bool $isDisabledBefore,
        bool $isDisabledAfter,
        string $reportStatusBefore,
        string $reportStatusAfter,
        ?string $adminNote
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => 'user.suspended',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'description' => 'User suspended.',
            'metadata' => [
                'report_id' => $report->id,
                'reported_user_id' => $user->id,
                'account_status_from' => $accountStatusBefore,
                'account_status_to' => $accountStatusAfter,
                'is_disabled_from' => $isDisabledBefore,
                'is_disabled_to' => $isDisabledAfter,
                'report_status_from' => $reportStatusBefore,
                'report_status_to' => $reportStatusAfter,
                'admin_note' => $adminNote,
                'moderation_action' => 'suspend_user',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePostReport(PostReport $report): array
    {
        return [
            'id' => $report->id,
            'type' => 'listing',
            'listing_id' => (int) $report->listing_id,
            'reporter_user_id' => (int) $report->reporter_user_id,
            'reason_category' => (string) $report->reason_category,
            'description' => (string) $report->description,
            'status' => (string) $report->status,
            'evidence_path' => $report->evidence_path,
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
            'listing' => $report->listing ? [
                'id' => $report->listing->id,
                'user_id' => (int) $report->listing->user_id,
                'title' => $report->listing->title,
                'listing_status' => $report->listing->listing_status,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUserReport(UserReport $report): array
    {
        return [
            'id' => $report->id,
            'type' => 'user',
            'reported_user_id' => (int) $report->reported_user_id,
            'reporter_user_id' => (int) $report->reporter_user_id,
            'reason_category' => (string) $report->reason_category,
            'description' => (string) $report->description,
            'status' => (string) $report->status,
            'evidence_path' => $report->evidence_path,
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
            'user' => $this->transformUser($report->reportedUser),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListingModeration(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'user_id' => (int) $listing->user_id,
            'title' => $listing->title,
            'listing_status' => (string) $listing->listing_status,
            'is_flagged' => (bool) $listing->is_flagged,
            'moderation_note' => $listing->moderation_note,
            'updated_at' => $listing->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeModeratedUser(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->fullName(),
            'status' => $user->apiStatus(),
            'is_disabled' => Schema::hasColumn('users', 'is_disabled')
                ? (bool) $user->is_disabled
                : null,
            'disabled_at' => Schema::hasColumn('users', 'disabled_at')
                ? $user->disabled_at?->toISOString()
                : null,
            'suspended_until' => Schema::hasColumn('users', 'suspended_until')
                ? $user->suspended_until?->toISOString()
                : null,
            'suspended_reason' => Schema::hasColumn('users', 'suspended_reason')
                ? $user->suspended_reason
                : null,
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePostReportStatusHistory(PostReportStatusHistory $history): array
    {
        return [
            'id' => $history->id,
            'status' => (string) $history->status,
            'admin_note' => $history->admin_note,
            'changed_by' => $history->changed_by !== null ? (int) $history->changed_by : null,
            'changed_by_user' => $this->transformUser($history->changedBy),
            'created_at' => $history->created_at?->toISOString(),
            'updated_at' => $history->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUserReportStatusHistory(UserReportStatusHistory $history): array
    {
        return [
            'id' => $history->id,
            'status' => (string) $history->status,
            'admin_note' => $history->admin_note,
            'changed_by' => $history->changed_by !== null ? (int) $history->changed_by : null,
            'changed_by_user' => $this->transformUser($history->changedBy),
            'created_at' => $history->created_at?->toISOString(),
            'updated_at' => $history->updated_at?->toISOString(),
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

    /**
     * @return array<string, int>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function userAccountStatusColumn(): string
    {
        return Schema::hasColumn('users', 'status') ? 'status' : 'account_status';
    }

    private function userAccountStatus(User $user): string
    {
        $column = $this->userAccountStatusColumn();

        return (string) ($user->{$column} ?? '');
    }

    private function isAdminUser(User $user): bool
    {
        $user->loadMissing('roles:id,code');

        return $user->role === 'admin'
            || $user->roles->contains(static fn ($role): bool => $role->code === 'admin');
    }

    private function deleteStoredEvidencePath(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = Storage::disk('public');
        $disk->delete($path);

        $directory = dirname($path);
        if ($directory === '.' || $directory === '') {
            return;
        }

        if ($disk->allFiles($directory) === [] && $disk->allDirectories($directory) === []) {
            $disk->deleteDirectory($directory);
        }
    }
}
