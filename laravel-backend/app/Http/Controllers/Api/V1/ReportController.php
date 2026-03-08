<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIndexRequest;
use App\Http\Requests\ShowReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Models\ActivityLog;
use App\Models\Listing;
use App\Models\ModerationReport;
use App\Models\ReportEvidence;
use App\Models\User;
use App\Support\ApiResponse;
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

        $report = $this->createReport($request, [
            'target_type' => ModerationReport::TARGET_TYPE_LISTING,
            'target_listing_id' => $reportableListing->id,
            'target_user_id' => null,
        ]);

        return ApiResponse::success('Report submitted.', [
            'report' => $this->serializeReport(
                $this->reportQuery()->whereKey($report->id)->firstOrFail()
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

        $report = $this->createReport($request, [
            'target_type' => ModerationReport::TARGET_TYPE_USER,
            'target_listing_id' => null,
            'target_user_id' => $user->id,
        ]);

        return ApiResponse::success('Report submitted.', [
            'report' => $this->serializeReport(
                $this->reportQuery()->whereKey($report->id)->firstOrFail()
            ),
        ], 201);
    }

    public function mine(ReportIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $reporterId = (int) $request->user()->id;

        $paginator = $this->reportQuery()
            ->where('reporter_user_id', $reporterId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Reports retrieved successfully.', [
            'reports' => array_map(
                fn (ModerationReport $report): array => $this->serializeReport($report),
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

    public function show(ShowReportRequest $request, ModerationReport $report): JsonResponse
    {
        return ApiResponse::success('Report retrieved successfully.', [
            'report' => $this->serializeReport(
                $this->reportQuery()->whereKey($report->id)->firstOrFail()
            ),
        ]);
    }

    private function createReport(StoreReportRequest $request, array $targetAttributes): ModerationReport
    {
        $validated = $request->validated();
        $evidence = $request->file('evidence');
        $storedPath = null;
        $report = null;

        try {
            DB::transaction(function () use ($request, $validated, $targetAttributes, $evidence, &$storedPath, &$report): void {
                $report = ModerationReport::query()->create([
                    'reporter_user_id' => (int) $request->user()->id,
                    'report_category' => (string) $validated['report_category'],
                    'description' => (string) $validated['description'],
                    'status' => ModerationReport::STATUS_PENDING,
                    'priority' => ModerationReport::PRIORITY_MEDIUM,
                    'resolution_action' => ModerationReport::RESOLUTION_ACTION_NONE,
                    ...$targetAttributes,
                ]);

                if ($evidence instanceof UploadedFile) {
                    $storedPath = $evidence->store('reports/'.$report->id, 'public');

                    ReportEvidence::query()->create([
                        'moderation_report_id' => $report->id,
                        'uploaded_by_user_id' => (int) $request->user()->id,
                        'file_path' => $storedPath,
                        'mime_type' => (string) ($evidence->getMimeType() ?? 'application/octet-stream'),
                        'file_size_bytes' => $this->resolveFileSize($evidence),
                        'sha256_hash' => $this->resolveSha256Hash($evidence),
                        'caption' => null,
                    ]);
                }

                $this->logSubmissionActivity($request, $report, $storedPath !== null);
            });
        } catch (\Throwable $exception) {
            $this->deleteStoredEvidencePath($storedPath);

            throw $exception;
        }

        if (! $report instanceof ModerationReport) {
            throw new \RuntimeException('Unable to create report.');
        }

        return $report;
    }

    private function reportQuery(): Builder
    {
        return ModerationReport::query()->with([
            'targetListing:id,user_id,title,listing_status',
            'targetUser:id,first_name,middle_name,last_name',
            'evidence' => static function ($query): void {
                $query
                    ->select([
                        'id',
                        'moderation_report_id',
                        'uploaded_by_user_id',
                        'file_path',
                        'mime_type',
                        'file_size_bytes',
                        'caption',
                        'created_at',
                    ])
                    ->orderBy('id');
            },
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
        ModerationReport $report,
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
                'target_type' => (string) $report->target_type,
                'target_listing_id' => $report->target_listing_id === null ? null : (int) $report->target_listing_id,
                'target_user_id' => $report->target_user_id === null ? null : (int) $report->target_user_id,
                'report_category' => (string) $report->report_category,
                'has_evidence' => $hasEvidence,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(ModerationReport $report): array
    {
        $evidence = $report->evidence->first();

        return [
            'id' => $report->id,
            'reporter_user_id' => (int) $report->reporter_user_id,
            'target_type' => (string) $report->target_type,
            'target_listing_id' => $report->target_listing_id === null ? null : (int) $report->target_listing_id,
            'target_user_id' => $report->target_user_id === null ? null : (int) $report->target_user_id,
            'report_category' => (string) $report->report_category,
            'description' => (string) $report->description,
            'status' => (string) $report->status,
            'priority' => (string) $report->priority,
            'resolution_action' => (string) $report->resolution_action,
            'resolution_notes' => $report->resolution_notes,
            'resolved_at' => $report->resolved_at?->toISOString(),
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
            'listing' => $report->targetListing ? [
                'id' => $report->targetListing->id,
                'user_id' => (int) $report->targetListing->user_id,
                'title' => $report->targetListing->title,
                'listing_status' => $report->targetListing->listing_status,
            ] : null,
            'user' => $this->transformUser($report->targetUser),
            'evidence' => $this->transformEvidence($evidence),
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
     * @return array<string, mixed>|null
     */
    private function transformEvidence(?ReportEvidence $evidence): ?array
    {
        if (! $evidence) {
            return null;
        }

        return [
            'id' => $evidence->id,
            'file_path' => $evidence->file_path,
            'mime_type' => $evidence->mime_type,
            'file_size_bytes' => (int) $evidence->file_size_bytes,
            'uploaded_by_user_id' => $evidence->uploaded_by_user_id === null ? null : (int) $evidence->uploaded_by_user_id,
            'caption' => $evidence->caption,
            'created_at' => $evidence->created_at?->toISOString(),
        ];
    }

    private function resolveFileSize(UploadedFile $file): int
    {
        $size = $file->getSize();

        if (is_int($size) && $size > 0) {
            return $size;
        }

        $realPath = $file->getRealPath();

        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            $resolvedSize = filesize($realPath);

            if (is_int($resolvedSize) && $resolvedSize > 0) {
                return $resolvedSize;
            }
        }

        return 1;
    }

    private function resolveSha256Hash(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            return null;
        }

        $hash = hash_file('sha256', $realPath);

        return is_string($hash) && $hash !== '' ? $hash : null;
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
