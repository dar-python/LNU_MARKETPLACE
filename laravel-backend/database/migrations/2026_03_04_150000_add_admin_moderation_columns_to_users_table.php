<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasRole = Schema::hasColumn('users', 'role');
        $hasIsApproved = Schema::hasColumn('users', 'is_approved');
        $hasApprovedAt = Schema::hasColumn('users', 'approved_at');
        $hasApprovedByUserId = Schema::hasColumn('users', 'approved_by_user_id');
        $hasDeclinedAt = Schema::hasColumn('users', 'declined_at');
        $hasDeclinedByUserId = Schema::hasColumn('users', 'declined_by_user_id');
        $hasDeclinedReason = Schema::hasColumn('users', 'declined_reason');
        $hasIsDisabled = Schema::hasColumn('users', 'is_disabled');
        $hasDisabledAt = Schema::hasColumn('users', 'disabled_at');

        Schema::table('users', function (Blueprint $table) use (
            $hasRole,
            $hasIsApproved,
            $hasApprovedAt,
            $hasApprovedByUserId,
            $hasDeclinedAt,
            $hasDeclinedByUserId,
            $hasDeclinedReason,
            $hasIsDisabled,
            $hasDisabledAt
        ): void {
            if (! $hasRole) {
                $table->string('role')->default('user')->after('password');
                $table->index('role');
            }

            if (! $hasIsApproved) {
                $table->boolean('is_approved')->default(false)->after('account_status');
            }

            if (! $hasApprovedAt) {
                $table->timestamp('approved_at')->nullable()->after('is_approved');
            }

            if (! $hasApprovedByUserId) {
                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! $hasDeclinedAt) {
                $table->timestamp('declined_at')->nullable()->after('approved_by_user_id');
            }

            if (! $hasDeclinedByUserId) {
                $table->foreignId('declined_by_user_id')
                    ->nullable()
                    ->after('declined_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! $hasDeclinedReason) {
                $table->text('declined_reason')->nullable()->after('declined_by_user_id');
            }

            if (! $hasIsDisabled) {
                $table->boolean('is_disabled')->default(false)->after('declined_reason');
            }

            if (! $hasDisabledAt) {
                $table->timestamp('disabled_at')->nullable()->after('is_disabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'approved_by_user_id')) {
                $table->dropConstrainedForeignId('approved_by_user_id');
            }

            if (Schema::hasColumn('users', 'declined_by_user_id')) {
                $table->dropConstrainedForeignId('declined_by_user_id');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('users', 'is_approved') ? 'is_approved' : null,
                Schema::hasColumn('users', 'approved_at') ? 'approved_at' : null,
                Schema::hasColumn('users', 'declined_at') ? 'declined_at' : null,
                Schema::hasColumn('users', 'declined_reason') ? 'declined_reason' : null,
                Schema::hasColumn('users', 'is_disabled') ? 'is_disabled' : null,
                Schema::hasColumn('users', 'disabled_at') ? 'disabled_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }

            // Intentionally keep the role column on rollback to avoid dropping
            // an existing role column in environments where it predated this migration.
        });
    }
};
