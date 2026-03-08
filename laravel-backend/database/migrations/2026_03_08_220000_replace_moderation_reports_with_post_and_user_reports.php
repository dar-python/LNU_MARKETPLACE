<?php

use App\Models\PostReport;
use App\Models\UserReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;

return new class extends Migration
{
    private const LEGACY_REPORT_SUBJECT_TYPE = 'App\\Models\\ModerationReport';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->restrictOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason_category', 50);
            $table->text('description');
            $table->string('status', 50)->default(PostReport::STATUS_SUBMITTED);
            $table->string('evidence_path')->nullable();
            $table->timestamps();

            $table->index(['reporter_user_id', 'created_at']);
            $table->index(['listing_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason_category', 50);
            $table->text('description');
            $table->string('status', 50)->default(UserReport::STATUS_SUBMITTED);
            $table->string('evidence_path')->nullable();
            $table->timestamps();

            $table->index(['reporter_user_id', 'created_at']);
            $table->index(['reported_user_id', 'created_at']);
            $table->index('status');
        });

        DB::statement('ALTER TABLE user_reports ADD CONSTRAINT chk_user_reports_no_self_target CHECK (reporter_user_id <> reported_user_id)');

        if (! Schema::hasTable('moderation_reports')) {
            return;
        }

        foreach ($this->legacyReports() as $legacyReport) {
            $payload = [
                'id' => (int) $legacyReport->id,
                'reporter_user_id' => (int) $legacyReport->reporter_user_id,
                'reason_category' => $this->mapLegacyReasonCategory($legacyReport->report_category),
                'description' => (string) $legacyReport->description,
                'status' => $this->normalizeLegacyStatus($legacyReport->status),
                'evidence_path' => $legacyReport->evidence_path,
                'created_at' => $legacyReport->created_at,
                'updated_at' => $legacyReport->updated_at,
            ];

            if ($legacyReport->target_type === 'user' && $legacyReport->target_user_id !== null) {
                DB::table('user_reports')->insert([
                    ...$payload,
                    'reported_user_id' => (int) $legacyReport->target_user_id,
                ]);

                $this->updateLegacyActivityLogs((int) $legacyReport->id, UserReport::class);

                continue;
            }

            if ($legacyReport->target_listing_id === null) {
                continue;
            }

            DB::table('post_reports')->insert([
                ...$payload,
                'listing_id' => (int) $legacyReport->target_listing_id,
            ]);

            $this->updateLegacyActivityLogs((int) $legacyReport->id, PostReport::class);
        }

        if (Schema::hasTable('report_evidence')) {
            Schema::drop('report_evidence');
        }

        Schema::drop('moderation_reports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reports');
        Schema::dropIfExists('post_reports');

        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('target_type', ['listing', 'user']);
            $table->foreignId('target_listing_id')->nullable()->constrained('listings')->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('report_category', ['spam', 'fraud', 'prohibited_item', 'harassment', 'fake_listing', 'other']);
            $table->text('description');
            $table->enum('status', ['pending', 'under_review', 'resolved', 'rejected'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('assigned_admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('resolution_action', ['none', 'warning', 'listing_removed', 'account_suspended', 'account_banned'])->default('none');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['target_type', 'target_listing_id']);
            $table->index(['target_type', 'target_user_id']);
            $table->index(['assigned_admin_user_id', 'status']);
        });

        DB::statement("
            ALTER TABLE moderation_reports
            ADD CONSTRAINT chk_moderation_reports_target_xor CHECK (
                (target_type = 'listing' AND target_listing_id IS NOT NULL AND target_user_id IS NULL)
                OR
                (target_type = 'user' AND target_user_id IS NOT NULL AND target_listing_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE moderation_reports
            ADD CONSTRAINT chk_moderation_reports_no_self_user_target CHECK (
                target_type <> 'user' OR reporter_user_id <> target_user_id
            )
        ");

        Schema::create('report_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderation_report_id')->constrained('moderation_reports')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size_bytes');
            $table->char('sha256_hash', 64)->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE report_evidence ADD CONSTRAINT chk_report_evidence_file_size_positive CHECK (file_size_bytes > 0)');
    }

    private function legacyReports(): LazyCollection
    {
        $query = DB::table('moderation_reports')
            ->select([
                'moderation_reports.id',
                'moderation_reports.reporter_user_id',
                'moderation_reports.target_type',
                'moderation_reports.target_listing_id',
                'moderation_reports.target_user_id',
                'moderation_reports.report_category',
                'moderation_reports.description',
                'moderation_reports.status',
                'moderation_reports.created_at',
                'moderation_reports.updated_at',
            ]);

        if (Schema::hasTable('report_evidence')) {
            $firstEvidence = DB::table('report_evidence')
                ->select('moderation_report_id', DB::raw('MIN(id) as min_id'))
                ->groupBy('moderation_report_id');

            $query
                ->leftJoinSub($firstEvidence, 'first_evidence', function ($join): void {
                    $join->on('first_evidence.moderation_report_id', '=', 'moderation_reports.id');
                })
                ->leftJoin('report_evidence as evidence', 'evidence.id', '=', 'first_evidence.min_id')
                ->addSelect('evidence.file_path as evidence_path');
        } else {
            $query->addSelect(DB::raw('NULL as evidence_path'));
        }

        return $query
            ->orderBy('moderation_reports.id')
            ->cursor();
    }

    private function mapLegacyReasonCategory(mixed $reasonCategory): string
    {
        return match ((string) $reasonCategory) {
            'fraud', 'fake_listing' => 'scam',
            'prohibited_item' => 'prohibited_item',
            'harassment' => 'harassment',
            'spam' => 'spam',
            'other' => 'other',
            default => 'other',
        };
    }

    private function normalizeLegacyStatus(mixed $status): string
    {
        $normalized = trim((string) $status);

        return $normalized === '' ? PostReport::STATUS_SUBMITTED : $normalized;
    }

    private function updateLegacyActivityLogs(int $legacyReportId, string $subjectType): void
    {
        DB::table('activity_logs')
            ->where('subject_type', self::LEGACY_REPORT_SUBJECT_TYPE)
            ->where('subject_id', $legacyReportId)
            ->update([
                'subject_type' => $subjectType,
            ]);
    }
};
