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
        if (Schema::hasColumn('inquiries', 'preferred_contact_method')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('preferred_contact_method', 20)
                ->default('email')
                ->after('message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('inquiries', 'preferred_contact_method')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn('preferred_contact_method');
        });
    }
};
