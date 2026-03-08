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
        Schema::create('post_report_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_report_id')->constrained('post_reports')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('admin_note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['post_report_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('user_report_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_report_id')->constrained('user_reports')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('admin_note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_report_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_report_status_histories');
        Schema::dropIfExists('post_report_status_histories');
    }
};
