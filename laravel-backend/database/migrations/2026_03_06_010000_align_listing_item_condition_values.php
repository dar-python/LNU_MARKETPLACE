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

        // Expand enum first so value remapping can be done safely.
        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('brandnew','preowned','new','like_new','good','fair','poor') NOT NULL");
        DB::statement("UPDATE listings SET item_condition = 'brandnew' WHERE item_condition = 'new'");
        DB::statement("UPDATE listings SET item_condition = 'preowned' WHERE item_condition IN ('like_new','good','fair','poor')");
        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('brandnew','preowned') NOT NULL");
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

        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('brandnew','preowned','new','like_new','good','fair','poor') NOT NULL");
        DB::statement("UPDATE listings SET item_condition = 'new' WHERE item_condition = 'brandnew'");
        DB::statement("UPDATE listings SET item_condition = 'good' WHERE item_condition = 'preowned'");
        DB::statement("ALTER TABLE listings MODIFY item_condition ENUM('new','like_new','good','fair','poor') NOT NULL");
    }
};
