<?php

namespace Tests\Feature;

use App\Mail\EmailOtpMail;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lnu.allowed_email_domains', ['lnu.edu.ph']);
        config()->set('lnu.allowed_student_id_prefixes', ['230']);
        config()->set('lnu.student_id_prefix_length', 3);
        config()->set('lnu.enforce_email_student_id_match', true);
        config()->set('lnu.password_uncompromised', false);
        config()->set('lnu.email_otp_expires_minutes', 10);
        config()->set('sanctum.stateful', []);

        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => '230'],
            [
                'enrollment_year' => 2023,
                'is_active' => true,
                'notes' => 'Test prefix',
            ]
        );

        Role::query()->create([
            'code' => 'user',
            'name' => 'User',
            'description' => 'Default student account',
            'is_system' => true,
        ]);
    }

    public function test_register_returns_pending_user_and_creates_verification_and_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'student_id' => '2301234',
            'email' => '2301234@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful. Please verify your email using the OTP.')
            ->assertJsonPath('data.user.student_id', '2301234')
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonStructure(['trace_id']);

        $user = User::query()->where('student_id', '2301234')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('2301234@lnu.edu.ph', $user->email);

        if (Schema::hasColumn('users', 'status')) {
            $this->assertSame('pending_verification', (string) $user->status);
        } else {
            $this->assertSame('pending_verification', (string) $user->account_status);
        }

        $verification = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->latest('id')
            ->first();
        $this->assertNotNull($verification);
        $this->assertSame('2301234@lnu.edu.ph', $verification->sent_to_email);
        $this->assertNotNull($verification->otp_hash);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_register_validation_uses_standard_error_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'student_id' => '2301235',
            'email' => 'jane@not-allowed.com',
            'password' => 'JaneDoe!2301235',
            'password_confirmation' => 'JaneDoe!2301235',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['email', 'password'],
                'trace_id',
            ]);
    }

    public function test_register_validation_errors_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['name', 'student_id', 'password'],
                'trace_id',
            ]);
    }

    public function test_register_student_id_validation_matrix(): void
    {
        config()->set('lnu.allowed_student_id_prefixes', ['230', '240']);
        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => '240'],
            [
                'enrollment_year' => 2024,
                'is_active' => true,
                'notes' => 'Test prefix',
            ]
        );

        $validStudentIds = ['2302201', '2409999'];
        $invalidStudentIds = ['230123', '23012345', 'ABC1234', '2501234', '23A1234'];

        foreach ($validStudentIds as $index => $studentId) {
            $response = $this->postJson('/api/v1/auth/register', [
                'name' => 'Valid Student '.$index,
                'student_id' => $studentId,
                'email' => $studentId.'@lnu.edu.ph',
                'password' => 'Safe!Pass123',
                'password_confirmation' => 'Safe!Pass123',
            ]);

            $response
                ->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.user.student_id', $studentId);
        }

        foreach ($invalidStudentIds as $index => $studentId) {
            $response = $this->postJson('/api/v1/auth/register', [
                'name' => 'Invalid Student '.$index,
                'student_id' => $studentId,
                'email' => 'invalid'.$index.'@lnu.edu.ph',
                'password' => 'Safe!Pass123',
                'password_confirmation' => 'Safe!Pass123',
            ]);

            $response
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Validation failed.')
                ->assertJsonStructure(['errors' => ['student_id'], 'trace_id']);
        }
    }

    public function test_login_blocks_unverified_email_with_code(): void
    {
        $user = $this->createUser('2301236', 'pending_verification', '2301236@lnu.edu.ph', false);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->student_id,
            'password' => 'Safe!Pass123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Email not verified yet.')
            ->assertJsonPath('errors.code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_login_blocks_suspended_accounts_with_code(): void
    {
        $user = $this->createUser('2301232', 'suspended', '2301232@lnu.edu.ph', true);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->student_id,
            'password' => 'Safe!Pass123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Account suspended.')
            ->assertJsonPath('errors.code', 'ACCOUNT_SUSPENDED')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_register_email_mismatch_returns_error_under_email_field(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Email Mismatch User',
            'student_id' => '2301250',
            'email' => '2309999@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email.0', 'Email must match Student ID.')
            ->assertJsonMissingPath('errors.student_id')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_register_with_email_requires_otp_then_user_can_login_after_verification(): void
    {
        Mail::fake();

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Email User',
            'student_id' => '2301240',
            'email' => '2301240@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registration successful. Please verify your email using the OTP.')
            ->assertJsonPath('data.user.status', 'pending');

        $user = User::query()->where('student_id', '2301240')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action_type' => 'auth.registered',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action_type' => 'auth.email_otp_sent',
        ]);

        $verification = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $this->assertNotNull($verification);
        $this->assertNotNull($verification->sent_to_email);
        $this->assertNotNull($verification->otp_hash);
        $this->assertNotNull($verification->expires_at);

        $otp = null;
        Mail::assertSent(EmailOtpMail::class, function (EmailOtpMail $mail) use (&$otp): bool {
            $otp = $mail->otp;

            return true;
        });
        $this->assertNotNull($otp);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '2301240@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Email not verified yet.')
            ->assertJsonPath('errors.code', 'EMAIL_NOT_VERIFIED');
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action_type' => 'auth.login_blocked',
        ]);

        $this->postJson('/api/v1/auth/email/otp/verify', [
            'identifier' => '2301240@lnu.edu.ph',
            'otp' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Email verified.');
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action_type' => 'auth.email_otp_verified',
        ]);

        $user->refresh();
        $verification->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('verified', $verification->status);

        if (Schema::hasColumn('users', 'status')) {
            $this->assertSame('active', (string) $user->status);
        } else {
            $this->assertSame('active', (string) $user->account_status);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '2301240@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.');
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action_type' => 'auth.login_success',
        ]);
    }

    public function test_email_otp_resend_endpoint_is_throttled(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Resend User',
            'student_id' => '2301241',
            'email' => '2301241@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'password_confirmation' => 'Safe!Pass123',
        ])->assertCreated();

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/auth/email/otp/resend', [
                'identifier' => '2301241@lnu.edu.ph',
            ])
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'OTP resent.');
        }

        $this->postJson('/api/v1/auth/email/otp/resend', [
            'identifier' => '2301241@lnu.edu.ph',
        ])
            ->assertStatus(429);
    }

    public function test_login_returns_token_for_approved_account(): void
    {
        $user = $this->createUser('2301237', 'active', 'student@lnu.edu.ph');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student@lnu.edu.ph',
            'password' => 'Safe!Pass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.status', 'approved')
            ->assertJsonPath('data.user.roles.0', 'user')
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'student_id', 'email', 'status', 'roles']],
                'trace_id',
            ]);
    }

    public function test_login_with_wrong_password_returns_401_in_standard_error_envelope(): void
    {
        $user = $this->createUser('2301299', 'active', 'wrong-pass@lnu.edu.ph');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->student_id,
            'password' => 'Wrong!Pass123',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser('2301238', 'active');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged out.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $user->id,
            'action_type' => 'auth.logout',
        ]);
    }

    public function test_logout_invalidates_token_and_blocks_me_endpoint_afterward(): void
    {
        $user = $this->createUser('2301210', 'active');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_me_requires_auth_and_returns_user_profile(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $user = $this->createUser('2301239', 'active');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.student_id', '2301239')
            ->assertJsonPath('data.user.status', 'approved')
            ->assertJsonPath('data.user.roles.0', 'user')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_unhandled_exception_returns_standard_500_envelope(): void
    {
        Route::middleware('api')->get('/api/v1/test/boom', function () {
            throw new \RuntimeException('boom');
        });

        $this->getJson('/api/v1/test/boom')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Server error.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    private function createUser(string $studentId, string $status, ?string $email = null, bool $emailVerified = true): User
    {
        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $email,
            'password' => 'Safe!Pass123',
            'first_name' => 'Sample',
            'last_name' => 'Student',
            'middle_name' => null,
            'email_verified_at' => $email !== null && $emailVerified ? now() : null,
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = $status;
        } else {
            $attributes['account_status'] = $status;
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
