<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement(
            "ALTER TABLE listings MODIFY item_condition ENUM('brandnew','preowned','new','used','like_new','good','fair','poor') NULL"
        );
        DB::statement("UPDATE listings SET item_condition = 'new' WHERE item_condition = 'brandnew'");
        DB::statement(
            "UPDATE listings SET item_condition = 'used' WHERE item_condition IN ('preowned','like_new','good','fair','poor')"
        );
        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('new','used') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('new','used','brandnew','preowned') NULL");
        DB::statement("UPDATE listings SET item_condition = 'brandnew' WHERE item_condition = 'new'");
        DB::statement("UPDATE listings SET item_condition = 'preowned' WHERE item_condition = 'used'");
        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('brandnew','preowned') NOT NULL");
    }
};
