<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('inquiries', 'status')) {
                $table->string('status', 20)->default('pending');
            }

            if (! Schema::hasColumn('inquiries', 'decided_at')) {
                $table->timestamp('decided_at')->nullable();
            }

            if (! Schema::hasColumn('inquiries', 'decided_by')) {
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('inquiries', 'inquiry_status')) {
            DB::table('inquiries')
                ->whereNull('status')
                ->whereIn('inquiry_status', ['new', 'read'])
                ->update([
                    'status' => 'pending',
                    'decided_at' => null,
                    'decided_by' => null,
                ]);

            DB::table('inquiries')
                ->whereNull('status')
                ->where('inquiry_status', 'resolved')
                ->update([
                    'status' => 'accepted',
                    'decided_at' => DB::raw('COALESCE(responded_at, updated_at, created_at)'),
                    'decided_by' => DB::raw('recipient_user_id'),
                ]);

            DB::table('inquiries')
                ->whereNull('status')
                ->where('inquiry_status', 'closed')
                ->update([
                    'status' => 'declined',
                    'decided_at' => DB::raw('COALESCE(responded_at, updated_at, created_at)'),
                    'decided_by' => DB::raw('recipient_user_id'),
                ]);
        }

        DB::table('inquiries')
            ->whereNull('status')
            ->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('inquiries', 'decided_by')) {
                $table->dropConstrainedForeignId('decided_by');
            }

            if (Schema::hasColumn('inquiries', 'decided_at')) {
                $table->dropColumn('decided_at');
            }

            if (Schema::hasColumn('inquiries', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
