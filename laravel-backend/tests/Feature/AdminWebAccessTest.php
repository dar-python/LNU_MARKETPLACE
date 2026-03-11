<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminWebAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Admin Sign In');
    }

    public function test_admin_can_log_in_to_dashboard(): void
    {
        $this->withoutMiddleware();

        $admin = $this->createUser(true);
        $admin->forceFill(['password' => 'admin123'])->save();

        $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'admin123',
        ])->assertRedirect('/admin/listings');

        $this->followRedirects(
            $this->get('/admin/listings')
        )->assertOk()
            ->assertSee('Pending Listings');
    }

    public function test_non_admin_is_rejected_from_admin_login(): void
    {
        $this->withoutMiddleware();

        $user = $this->createUser(false);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'login' => $user->email,
                'password' => 'password',
            ])->assertRedirect('/admin/login')
            ->assertSessionHasErrors([
                'login' => 'Only administrators can access the admin dashboard.',
            ]);

        $this->assertGuest();
    }

    private function createUser(bool $isAdmin): User
    {
        $roleCode = $isAdmin ? 'admin' : 'user';

        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            [
                'name' => ucfirst($roleCode),
                'description' => ucfirst($roleCode).' role',
                'is_system' => true,
            ]
        );

        $attributes = [
            'student_id' => $isAdmin ? '2309001' : '2309002',
            'student_id_prefix' => '230',
            'email' => $isAdmin ? 'admin-web@lnu.edu.ph' : 'user-web@lnu.edu.ph',
            'password' => 'password',
            'first_name' => $isAdmin ? 'Admin' : 'Regular',
            'last_name' => $isAdmin ? 'User' : 'User',
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
        }

        if (Schema::hasColumn('users', 'role')) {
            $attributes['role'] = $roleCode;
        }

        $user = User::query()->create($attributes);

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $user;
    }
}
