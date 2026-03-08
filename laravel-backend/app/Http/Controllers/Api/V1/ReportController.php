<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIndexRequest;
use App\Http\Requests\ShowReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Models\ActivityLog;
use App\Models\Listing;
use App\Models\PostReport;
use App\Models\User;
use App\Models\UserReport;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
