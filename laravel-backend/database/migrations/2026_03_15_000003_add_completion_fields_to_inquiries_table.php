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
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('inquiries', 'proof_image_path')) {
                $table->string('proof_image_path')->nullable();
            }

            if (! Schema::hasColumn('inquiries', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });

        if (! Schema::hasColumn('inquiries', 'inquiry_status')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement(
            "ALTER TABLE inquiries MODIFY inquiry_status ENUM('new','read','resolved','closed','completed') NOT NULL DEFAULT 'new'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        if (
            Schema::hasColumn('inquiries', 'inquiry_status')
            && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
        ) {
            DB::table('inquiries')
                ->where('inquiry_status', 'completed')
                ->update(['inquiry_status' => 'resolved']);

            DB::statement(
                "ALTER TABLE inquiries MODIFY inquiry_status ENUM('new','read','resolved','closed') NOT NULL DEFAULT 'new'"
            );
        }

        Schema::table('inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('inquiries', 'proof_image_path')) {
                $table->dropColumn('proof_image_path');
            }

            if (Schema::hasColumn('inquiries', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
