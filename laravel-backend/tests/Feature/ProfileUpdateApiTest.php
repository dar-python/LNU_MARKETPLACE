<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lnu.allowed_student_id_prefixes', ['230']);
        config()->set('lnu.student_id_prefix_length', 3);
        config()->set('sanctum.stateful', []);

        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => '230'],
            [
                'enrollment_year' => 2023,
                'is_active' => true,
                'notes' => 'Test prefix',
            ]
        );

        Role::query()->firstOrCreate(
            ['code' => 'user'],
            [
                'name' => 'User',
                'description' => 'Default student account',
                'is_system' => true,
            ]
        );
    }

    public function test_authenticated_user_can_update_profile_details_and_fetch_them_from_me(): void
    {
        Storage::fake('public');

        $user = $this->createUser('2309901');
        $token = $user->createToken('test-token')->plainTextToken;
        $image = UploadedFile::fake()->image('profile.png', 640, 640)->size(256);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/auth/update-profile', [
                'contact_number' => '09171234567',
                'program' => 'BS Information Technology',
                'year_level' => '1st Year',
                'organization' => 'Developers Guild',
                'section' => 'A',
                'bio' => 'Building student-first marketplace tools.',
                'profile_picture' => $image,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('data.user.contact_number', '09171234567')
            ->assertJsonPath('data.user.program', 'BS Information Technology')
            ->assertJsonPath('data.user.year_level', '1st Year')
            ->assertJsonPath('data.user.organization', 'Developers Guild')
            ->assertJsonPath('data.user.section', 'A')
            ->assertJsonPath('data.user.bio', 'Building student-first marketplace tools.')
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'profile_picture_path',
                    ],
                ],
                'trace_id',
            ]);

        $user->refresh();

        $this->assertSame('09171234567', $user->contact_number);
        $this->assertSame('BS Information Technology', $user->program);
        $this->assertSame('1st Year', $user->year_level);
        $this->assertSame('Developers Guild', $user->organization);
        $this->assertSame('A', $user->section);
        $this->assertSame('Building student-first marketplace tools.', $user->bio);
        $this->assertNotNull($user->profile_picture_path);
        $this->assertStringStartsWith('profile-pictures/'.$user->id.'/', $user->profile_picture_path);
        Storage::disk('public')->assertExists($user->profile_picture_path);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.contact_number', '09171234567')
            ->assertJsonPath('data.user.program', 'BS Information Technology')
            ->assertJsonPath('data.user.year_level', '1st Year')
            ->assertJsonPath('data.user.organization', 'Developers Guild')
            ->assertJsonPath('data.user.section', 'A')
            ->assertJsonPath('data.user.bio', 'Building student-first marketplace tools.')
            ->assertJsonPath('data.user.profile_picture_path', $user->profile_picture_path);
    }

    private function createUser(string $studentId): User
    {
        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => 'Profile',
            'last_name' => 'Owner',
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
        }

        $user = User::query()->create($attributes);

        $role = Role::query()->where('code', 'user')->firstOrFail();
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $user;
    }
}
