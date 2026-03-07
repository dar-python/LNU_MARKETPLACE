<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'admin'])->get('/api/v1/test/admin-only', function () {
            return response()->json([
                'ok' => true,
            ]);
        });

        Role::query()->firstOrCreate(
            ['code' => 'user'],
            [
                'name' => 'User',
                'description' => 'Default student account',
                'is_system' => true,
            ]
        );

        Role::query()->firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Platform administrator',
                'is_system' => true,
            ]
        );
    }

    public function test_admin_middleware_blocks_non_admin_users(): void
    {
        Sanctum::actingAs($this->createUser('2308001'));

        $this->getJson('/api/v1/test/admin-only')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_admin_middleware_allows_admin_users(): void
    {
        $admin = $this->createUser('2308002', 'admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/test/admin-only')
            ->assertOk()
            ->assertExactJson([
                'ok' => true,
            ]);
    }

    private function createUser(string $studentId, string $roleCode = 'user'): User
    {
        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => 'Admin',
            'last_name' => 'Tester',
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
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $user;
    }
}
