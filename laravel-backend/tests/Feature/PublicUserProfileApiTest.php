<?php

namespace Tests\Feature;

use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicUserProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lnu.allowed_student_id_prefixes', ['230']);
        config()->set('lnu.student_id_prefix_length', 3);

        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => '230'],
            [
                'enrollment_year' => 2023,
                'is_active' => true,
                'notes' => 'Test prefix',
            ]
        );
    }

    public function test_public_profile_endpoint_only_returns_fields_marked_public(): void
    {
        $user = $this->createUser('2309911', [
            'contact_number' => '09171234567',
            'program' => 'BS Information Technology',
            'year_level' => '3rd Year',
            'organization' => 'Developers Guild',
            'section' => 'B',
            'bio' => 'Open to campus meetups.',
            'is_contact_public' => false,
            'is_program_public' => true,
            'is_year_level_public' => true,
            'is_organization_public' => false,
            'is_section_public' => true,
            'is_bio_public' => false,
        ]);

        $this
            ->getJson('/api/v1/users/'.$user->id.'/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Public Seller')
            ->assertJsonPath('data.user.contact_number', null)
            ->assertJsonPath('data.user.program', 'BS Information Technology')
            ->assertJsonPath('data.user.year_level', '3rd Year')
            ->assertJsonPath('data.user.organization', null)
            ->assertJsonPath('data.user.section', 'B')
            ->assertJsonPath('data.user.bio', null);
    }

    private function createUser(string $studentId, array $overrides = []): User
    {
        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => 'Public',
            'last_name' => 'Seller',
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
        }

        return User::query()->create(array_merge($attributes, $overrides));
    }
}
