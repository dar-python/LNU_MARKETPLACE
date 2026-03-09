<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::query()->firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Platform administrator',
                'is_system' => true,
            ]
        );

        $studentId = (string) config('lnu.admin_seed_student_id', '2303838');
        $studentIdPrefix = substr($studentId, 0, 3);
        $adminEmail = $studentId.'@lnu.edu.ph';
        $adminPassword = (string) config('lnu.admin_seed_password', 'admin123');

        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => $studentIdPrefix],
            [
                'enrollment_year' => 2023,
                'is_active' => true,
                'notes' => 'Seeded for admin account',
            ]
        );

        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => $studentIdPrefix,
            'email' => $adminEmail,
            'password' => bcrypt($adminPassword),
            'first_name' => 'Admin',
            'last_name' => 'Admin',
            'middle_name' => null,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'role')) {
            $attributes['role'] = 'admin';
        }

        if (Schema::hasColumn('users', 'is_approved')) {
            $attributes['is_approved'] = true;
        }

        if (Schema::hasColumn('users', 'approved_at')) {
            $attributes['approved_at'] = now();
        }

        if (Schema::hasColumn('users', 'approved_by_user_id')) {
            $attributes['approved_by_user_id'] = null;
        }

        if (Schema::hasColumn('users', 'declined_at')) {
            $attributes['declined_at'] = null;
        }

        if (Schema::hasColumn('users', 'declined_by_user_id')) {
            $attributes['declined_by_user_id'] = null;
        }

        if (Schema::hasColumn('users', 'declined_reason')) {
            $attributes['declined_reason'] = null;
        }

        if (Schema::hasColumn('users', 'is_disabled')) {
            $attributes['is_disabled'] = false;
        }

        if (Schema::hasColumn('users', 'disabled_at')) {
            $attributes['disabled_at'] = null;
        }

        $adminUser = User::query()
            ->where('email', $adminEmail)
            ->orWhere('student_id', $studentId)
            ->first();

        if ($adminUser) {
            $adminUser->fill($attributes)->save();
        } else {
            $adminUser = User::query()->create($attributes);
        }

        $adminUser->roles()->syncWithoutDetaching([
            $adminRole->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);
    }
}
