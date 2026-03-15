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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_contact_public')->default(false);
            $table->boolean('is_program_public')->default(true);
            $table->boolean('is_year_level_public')->default(true);
            $table->boolean('is_organization_public')->default(true);
            $table->boolean('is_section_public')->default(true);
            $table->boolean('is_bio_public')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'is_contact_public',
                'is_program_public',
                'is_year_level_public',
                'is_organization_public',
                'is_section_public',
                'is_bio_public',
            ]);
        });
    }
};
