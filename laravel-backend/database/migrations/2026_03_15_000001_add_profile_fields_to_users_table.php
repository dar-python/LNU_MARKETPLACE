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
            $table->string('contact_number')->nullable();
            $table->string('program')->nullable();
            $table->string('year_level')->nullable();
            $table->string('organization')->nullable();
            $table->string('section')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_picture_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_number',
                'program',
                'year_level',
                'organization',
                'section',
                'bio',
                'profile_picture_path',
            ]);
        });
    }
};
