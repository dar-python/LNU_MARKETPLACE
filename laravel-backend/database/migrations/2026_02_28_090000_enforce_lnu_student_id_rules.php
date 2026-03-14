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

        $duplicateNormalizedPrefixes = DB::table('student_id_prefixes')
            ->selectRaw("CASE WHEN CHAR_LENGTH(prefix) = 2 THEN CONCAT(prefix, '0') ELSE prefix END as normalized_prefix, COUNT(*) as aggregate_count")
            ->groupBy('normalized_prefix')
            ->having('aggregate_count', '>', 1)
            ->exists();

        if ($duplicateNormalizedPrefixes) {
            throw new RuntimeException(
                'Cannot enforce LNU student ID rules: duplicate student_id_prefixes would result when normalizing legacy 2-digit prefixes.'
            );
        }

        $hasUnconvertiblePrefixes = DB::table('student_id_prefixes')
            ->whereRaw("prefix NOT REGEXP '^(21|22|23|24|25|26|27|28)$'")
            ->whereRaw("prefix NOT REGEXP '^({$allowedAlternation})$'")
            ->exists();

        if ($hasUnconvertiblePrefixes) {
            throw new RuntimeException(
                'Cannot enforce LNU student ID rules: student_id_prefixes contains values that cannot be safely converted to allowed 3-digit prefixes.'
            );
        }

        $hasInvalidStudentIds = DB::table('users')
            ->whereRaw("student_id NOT REGEXP '^({$allowedAlternation})[0-9]{4}$'")
            ->exists();

        if ($hasInvalidStudentIds) {
            throw new RuntimeException(
                'Cannot enforce LNU student ID rules: users.student_id contains values outside the required 7-digit LNU pattern.'
            );
        }

        $this->dropCheckIfExists('users', 'chk_users_student_id_format');
        $this->dropCheckIfExists('users', 'chk_users_student_id_prefix_match');
        $this->dropCheckIfExists('student_id_prefixes', 'chk_student_id_prefixes_format');
        $this->dropForeignIfExists('users', 'users_student_id_prefix_foreign');

        DB::statement('ALTER TABLE student_id_prefixes MODIFY prefix CHAR(3) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY student_id CHAR(7) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY student_id_prefix CHAR(3) NOT NULL');

        DB::statement("UPDATE student_id_prefixes SET prefix = CONCAT(prefix, '0') WHERE CHAR_LENGTH(prefix) = 2");
        DB::statement('UPDATE users SET student_id_prefix = LEFT(student_id, 3)');

        $hasUnexpectedPrefixesAfterNormalization = DB::table('student_id_prefixes')
            ->whereRaw("prefix NOT REGEXP '^({$allowedAlternation})$'")
            ->exists();

        if ($hasUnexpectedPrefixesAfterNormalization) {
            throw new RuntimeException(
                'Cannot enforce LNU student ID rules: normalized student_id_prefixes still contains unsupported prefixes.'
            );
        }

        $hasUsersWithoutMatchingPrefix = DB::table('users')
            ->leftJoin('student_id_prefixes', 'users.student_id_prefix', '=', 'student_id_prefixes.prefix')
            ->whereNull('student_id_prefixes.prefix')
            ->exists();

        if ($hasUsersWithoutMatchingPrefix) {
            throw new RuntimeException(
                'Cannot enforce LNU student ID rules: some users do not have a matching student_id_prefixes record after normalization.'
            );
        }

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_student_id_prefix_foreign FOREIGN KEY (student_id_prefix) REFERENCES student_id_prefixes(prefix) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE student_id_prefixes ADD CONSTRAINT chk_student_id_prefixes_format CHECK (prefix REGEXP '^({$allowedAlternation})$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_student_id_format CHECK (student_id REGEXP '^({$allowedAlternation})[0-9]{4}$')");
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_student_id_prefix_match CHECK (student_id_prefix = LEFT(student_id, 3))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible to avoid unsafe rollback of normalized student identifiers.
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        } catch (\Throwable) {
            // Ignore when missing.
        }
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
