<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateReportActionRequest;
use App\Http\Requests\ReportDashboardRequest;
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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    /**
     * @var list<string>
     */
    private const OPEN_REPORT_STATUSES = [
        PostReport::STATUS_SUBMITTED,
        PostReport::STATUS_PENDING,
        PostReport::STATUS_UNDER_REVIEW,
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

    public function dashboardSummary(ReportDashboardRequest $request): JsonResponse
    {
        $filters = $this->normalizeDashboardFilters($request->validated());

        $listingCount = $this->includesListingReports($filters)
            ? $this->dashboardPostReportSummaryQuery($filters)->count()
            : 0;
        $userCount = $this->includesUserReports($filters)
            ? $this->dashboardUserReportSummaryQuery($filters)->count()
            : 0;

        $byStatus = array_fill_keys(PostReport::STATUSES, 0);
        $byReasonCategory = array_fill_keys(PostReport::REASON_CATEGORIES, 0);

        foreach ($this->dashboardGroupedCounts($this->dashboardPostReportSummaryQuery($filters), 'status') as $status => $count) {
            $byStatus[$status] += $count;
        }

        foreach ($this->dashboardGroupedCounts($this->dashboardUserReportSummaryQuery($filters), 'status') as $status => $count) {
            $byStatus[$status] += $count;
        }

        foreach ($this->dashboardGroupedCounts($this->dashboardPostReportSummaryQuery($filters), 'reason_category') as $reasonCategory => $count) {
            $byReasonCategory[$reasonCategory] += $count;
        }

        foreach ($this->dashboardGroupedCounts($this->dashboardUserReportSummaryQuery($filters), 'reason_category') as $reasonCategory => $count) {
            $byReasonCategory[$reasonCategory] += $count;
        }

        return ApiResponse::success('Report dashboard summary retrieved successfully.', [
            'summary' => [
                'overall' => [
                    'total_reports' => $listingCount + $userCount,
                    'total_listing_reports' => $listingCount,
                    'total_user_reports' => $userCount,
                    'open_reports' => array_sum(array_intersect_key($byStatus, array_flip(self::OPEN_REPORT_STATUSES))),
                ],
                'by_status' => $byStatus,
                'by_type' => [
                    'listing' => $listingCount,
                    'user' => $userCount,
                ],
                'by_reason_category' => $byReasonCategory,
            ],
        ]);
    }

    public function dashboardReports(ReportDashboardRequest $request): JsonResponse
    {
        $filters = $this->normalizeDashboardFilters($request->validated());
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');

        $query = $this->dashboardReportsQuery($filters);

        $paginator = DB::query()
            ->fromSub($query, 'dashboard_reports')
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id', $sortDir)
            ->paginate($perPage);

        return ApiResponse::success('Reports retrieved successfully.', [
            'reports' => array_map(
                fn (object $report): array => $this->serializeDashboardReport($report),
                $paginator->items()
            ),
            'meta' => $this->paginationMeta($paginator),
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

    /**
     * @param  array<string, mixed>  $filters
     */
    private function normalizeDashboardFilters(array $filters): array
    {
        if (isset($filters['date_from']) && is_string($filters['date_from'])) {
            $filters['date_from'] = Carbon::parse($filters['date_from'])->startOfDay();
        }

        if (isset($filters['date_to']) && is_string($filters['date_to'])) {
            $filters['date_to'] = Carbon::parse($filters['date_to'])->endOfDay();
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function includesListingReports(array $filters): bool
    {
        return ($filters['type'] ?? null) !== 'user';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function includesUserReports(array $filters): bool
    {
        return ($filters['type'] ?? null) !== 'listing';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dashboardReportsQuery(array $filters): QueryBuilder
    {
        $queries = [];

        if ($this->includesListingReports($filters)) {
            $queries[] = $this->dashboardPostReportListQuery($filters);
        }

        if ($this->includesUserReports($filters)) {
            $queries[] = $this->dashboardUserReportListQuery($filters);
        }

        $combined = array_shift($queries);

        if (! $combined instanceof QueryBuilder) {
            throw new \RuntimeException('Unable to build dashboard reports query.');
        }

        foreach ($queries as $query) {
            $combined->unionAll($query);
        }

        return $combined;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dashboardPostReportSummaryQuery(array $filters): QueryBuilder
    {
        if (! $this->includesListingReports($filters)) {
            return DB::table('post_reports')->whereRaw('1 = 0');
        }

        $query = DB::table('post_reports')
            ->leftJoin('listings', 'listings.id', '=', 'post_reports.listing_id');

        return $this->applyDashboardPostReportFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dashboardUserReportSummaryQuery(array $filters): QueryBuilder
    {
        if (! $this->includesUserReports($filters)) {
            return DB::table('user_reports')->whereRaw('1 = 0');
        }

        $query = DB::table('user_reports')
            ->leftJoin('users as reported_users', 'reported_users.id', '=', 'user_reports.reported_user_id');

        return $this->applyDashboardUserReportFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dashboardPostReportListQuery(array $filters): QueryBuilder
    {
        $query = DB::table('post_reports')
            ->leftJoin('listings', 'listings.id', '=', 'post_reports.listing_id')
            ->select([
                'post_reports.id',
                DB::raw("'listing' as type"),
                'post_reports.status',
                'post_reports.reason_category',
                'post_reports.description',
                'post_reports.reporter_user_id',
                'post_reports.evidence_path',
                'post_reports.created_at',
                'post_reports.updated_at',
                'post_reports.listing_id',
                DB::raw('NULL as reported_user_id'),
                'listings.title as listing_title',
                'listings.user_id as listing_owner_id',
                DB::raw('NULL as reported_user_first_name'),
                DB::raw('NULL as reported_user_middle_name'),
                DB::raw('NULL as reported_user_last_name'),
            ]);

        return $this->applyDashboardPostReportFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dashboardUserReportListQuery(array $filters): QueryBuilder
    {
        $query = DB::table('user_reports')
            ->leftJoin('users as reported_users', 'reported_users.id', '=', 'user_reports.reported_user_id')
            ->select([
                'user_reports.id',
                DB::raw("'user' as type"),
                'user_reports.status',
                'user_reports.reason_category',
                'user_reports.description',
                'user_reports.reporter_user_id',
                'user_reports.evidence_path',
                'user_reports.created_at',
                'user_reports.updated_at',
                DB::raw('NULL as listing_id'),
                'user_reports.reported_user_id',
                DB::raw('NULL as listing_title'),
                DB::raw('NULL as listing_owner_id'),
                'reported_users.first_name as reported_user_first_name',
                'reported_users.middle_name as reported_user_middle_name',
                'reported_users.last_name as reported_user_last_name',
            ]);

        return $this->applyDashboardUserReportFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function dashboardGroupedCounts(QueryBuilder $query, string $column): array
    {
        $results = $query
            ->select($column, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column);

        return collect($results)
            ->mapWithKeys(fn (mixed $count, mixed $key): array => [(string) $key => (int) $count])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDashboardPostReportFilters(QueryBuilder $query, array $filters): QueryBuilder
    {
        $this->applyDashboardCommonFilters($query, 'post_reports', $filters);

        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function (QueryBuilder $builder) use ($search): void {
                if (ctype_digit($search)) {
                    $builder->where('post_reports.id', (int) $search)
                        ->orWhere('listings.title', 'like', '%'.$search.'%');

                    return;
                }

                $builder->where('listings.title', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDashboardUserReportFilters(QueryBuilder $query, array $filters): QueryBuilder
    {
        $this->applyDashboardCommonFilters($query, 'user_reports', $filters);

        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function (QueryBuilder $builder) use ($search): void {
                if (ctype_digit($search)) {
                    $builder->where('user_reports.id', (int) $search)
                        ->orWhere('reported_users.first_name', 'like', '%'.$search.'%')
                        ->orWhere('reported_users.middle_name', 'like', '%'.$search.'%')
                        ->orWhere('reported_users.last_name', 'like', '%'.$search.'%')
                        ->orWhere('reported_users.email', 'like', '%'.$search.'%');

                    return;
                }

                $builder
                    ->where('reported_users.first_name', 'like', '%'.$search.'%')
                    ->orWhere('reported_users.middle_name', 'like', '%'.$search.'%')
                    ->orWhere('reported_users.last_name', 'like', '%'.$search.'%')
                    ->orWhere('reported_users.email', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDashboardCommonFilters(QueryBuilder $query, string $table, array $filters): void
    {
        if (isset($filters['status']) && is_string($filters['status'])) {
            $query->where($table.'.status', $filters['status']);
        }

        if (isset($filters['reason_category']) && is_string($filters['reason_category'])) {
            $query->where($table.'.reason_category', $filters['reason_category']);
        }

        if (isset($filters['date_from']) && $filters['date_from'] instanceof Carbon) {
            $query->where($table.'.created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to'] instanceof Carbon) {
            $query->where($table.'.created_at', '<=', $filters['date_to']);
        }
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
    private function serializeDashboardReport(object $report): array
    {
        $type = (string) $report->type;
        $listingId = $report->listing_id !== null ? (int) $report->listing_id : null;
        $reportedUserId = $report->reported_user_id !== null ? (int) $report->reported_user_id : null;

        return [
            'id' => (int) $report->id,
            'type' => $type,
            'listing_id' => $listingId,
            'reported_user_id' => $reportedUserId,
            'reporter_user_id' => (int) $report->reporter_user_id,
            'reason_category' => (string) $report->reason_category,
            'description' => (string) $report->description,
            'status' => (string) $report->status,
            'evidence_path' => $report->evidence_path,
            'created_at' => $this->serializeTimestamp($report->created_at),
            'updated_at' => $this->serializeTimestamp($report->updated_at),
            'listing' => $type === 'listing' ? [
                'id' => $listingId,
                'user_id' => $report->listing_owner_id !== null ? (int) $report->listing_owner_id : null,
                'title' => $report->listing_title,
            ] : null,
            'user' => $type === 'user' ? [
                'id' => $reportedUserId,
                'full_name' => $this->fullNameFromParts(
                    $report->reported_user_first_name ?? null,
                    $report->reported_user_middle_name ?? null,
                    $report->reported_user_last_name ?? null
                ),
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

    private function serializeTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toISOString();
    }

    private function fullNameFromParts(mixed $firstName, mixed $middleName, mixed $lastName): string
    {
        $firstName = is_string($firstName) ? $firstName : null;
        $middleName = is_string($middleName) && $middleName !== '' ? $middleName : null;
        $lastName = is_string($lastName) ? $lastName : null;

        if ($middleName === null && $firstName !== null && $lastName !== null && $firstName === $lastName) {
            return $firstName;
        }

        $parts = array_filter([
            $firstName,
            $middleName,
            $lastName,
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        return implode(' ', $parts);
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
