<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALLOWED_PREFIXES = ['210', '220', '230', '240', '250', '260', '270', '280'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('student_id_prefixes')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $allowedAlternation = implode('|', self::ALLOWED_PREFIXES);

        $this->dropCheckIfExists('student_id_prefixes', 'chk_student_id_prefixes_format');
        $this->dropCheckIfExists('users', 'chk_users_student_id_format');

        $now = now();
        $rows = array_map(static function (string $prefix) use ($now): array {
            return [
                'prefix' => $prefix,
                'enrollment_year' => 2000 + (int) substr($prefix, 0, 2),
                'is_active' => true,
                'notes' => 'Synced from allowed student ID prefixes.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, self::ALLOWED_PREFIXES);

        DB::table('student_id_prefixes')->upsert(
            $rows,
            ['prefix'],
            ['enrollment_year', 'is_active', 'notes', 'updated_at']
        );

        DB::statement("ALTER TABLE student_id_prefixes ADD CONSTRAINT chk_student_id_prefixes_format CHECK (prefix REGEXP '^({$allowedAlternation})$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_student_id_format CHECK (student_id REGEXP '^({$allowedAlternation})[0-9]{4}$')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible to avoid relaxing student ID policy unexpectedly.
    }

    private function dropCheckIfExists(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP CHECK {$constraint}");
            return;
        } catch (\Throwable) {
            // Try MariaDB fallback syntax below.
        }

        try {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        } catch (\Throwable) {
            // Ignore when missing.
        }
    }
};
