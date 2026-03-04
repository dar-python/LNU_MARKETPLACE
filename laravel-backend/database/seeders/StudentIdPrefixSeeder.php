<?php

namespace Database\Seeders;

use App\Models\StudentIdPrefix;
use Illuminate\Database\Seeder;

class StudentIdPrefixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $raw = (string) env('STUDENT_ID_PREFIXES', '');
        $tokens = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($tokens === []) {
            $tokens = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                (array) config('lnu.allowed_student_id_prefixes', ['210', '220', '230', '240', '250', '260', '270', '280'])
            )));
        }

        $allowedPrefixes = [];

        foreach ($tokens as $token) {
            if (preg_match('/^\d{2}$/', $token) === 1) {
                $year = 2000 + (int) $token;
                $prefix = $token.'0';
            } elseif (preg_match('/^\d{3}$/', $token) === 1 && str_ends_with($token, '0')) {
                $year = 2000 + (int) substr($token, 0, 2);
                $prefix = $token;
            } elseif (preg_match('/^\d{4}$/', $token) === 1) {
                $year = (int) $token;
                $prefix = substr($token, -2).'0';
            } else {
                continue;
            }

            $allowedPrefixes[$prefix] = [
                'prefix' => $prefix,
                'enrollment_year' => $year,
            ];
        }

        if ($allowedPrefixes === []) {
            $allowedPrefixes['230'] = [
                'prefix' => '230',
                'enrollment_year' => 2023,
            ];
        }

        foreach ($allowedPrefixes as $row) {
            StudentIdPrefix::query()->updateOrCreate(
                ['prefix' => $row['prefix']],
                [
                    'enrollment_year' => $row['enrollment_year'],
                    'is_active' => true,
                    'notes' => 'Seeded for local development',
                ]
            );
        }
    }
}
