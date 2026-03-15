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
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('inquiries', 'seller_confirmed_at')) {
                $table->timestamp('seller_confirmed_at')->nullable();
            }

            if (! Schema::hasColumn('inquiries', 'buyer_confirmed_at')) {
                $table->timestamp('buyer_confirmed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            if (Schema::hasColumn('inquiries', 'seller_confirmed_at')) {
                $table->dropColumn('seller_confirmed_at');
            }

            if (Schema::hasColumn('inquiries', 'buyer_confirmed_at')) {
                $table->dropColumn('buyer_confirmed_at');
            }
        });
    }
};
