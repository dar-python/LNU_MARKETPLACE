<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResendEmailOtpRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyEmailOtpRequest;
use App\Mail\EmailOtpMail;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\StudentVerification;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $studentId = (string) $validated['student_id'];
        $prefixLength = (int) config('lnu.student_id_prefix_length', 3);
        $studentIdPrefix = $this->resolveStudentPrefix($studentId, $prefixLength);
        $email = isset($validated['email']) && is_string($validated['email']) && $validated['email'] !== ''
            ? $validated['email']
            : null;

        if ($studentIdPrefix === null) {
            return ApiResponse::error('Validation failed.', [
                'student_id' => ['The selected student ID prefix is not allowed.'],
            ], 422);
        }

        [$firstName, $middleName, $lastName] = $this->splitName((string) $validated['name']);
        $otp = null;
        $otpExpiresAt = null;

        $user = DB::transaction(function () use ($request, $validated, $studentId, $studentIdPrefix, $firstName, $middleName, $lastName, $email, &$otp, &$otpExpiresAt): User {
            $user = User::query()->create([
                'student_id' => $studentId,
                'student_id_prefix' => $studentIdPrefix,
                'email' => $email,
                'password' => $validated['password'],
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                ...$this->pendingStatusAttributes(),
            ]);

            ['otp' => $otp, 'expires_at' => $otpExpiresAt] = $this->createPendingVerification(
                $user,
                $email,
                $request->ip()
            );

            $this->assignDefaultRole($user);

            return $user->load('roles');
        });

        if ($email !== null && is_string($otp) && $otpExpiresAt instanceof CarbonInterface) {
            $this->sendEmailOtp($email, $otp, $otpExpiresAt);
        }

        return ApiResponse::success(
            'Registration successful. Please verify your email using the OTP.',
            [
                'user' => $this->serializeUser($user, false),
                'requires_email_verification' => $email !== null,
            ],
            201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = (string) $validated['identifier'];
        $password = (string) $validated['password'];

        $query = User::query()->with('roles');
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $query->where('email', $identifier);
        } else {
            $query->where('student_id', $identifier);
        }

        $user = $query->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return ApiResponse::error('Invalid credentials.', null, 401);
        }

        if ($user->isBlockedFromLogin()) {
            return ApiResponse::error('Account suspended.', [
                'code' => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        if ($user->email !== null && $user->email_verified_at === null) {
            return ApiResponse::error('Email not verified yet.', [
                'code' => 'EMAIL_NOT_VERIFIED',
                'identifier' => $user->email,
            ], 403);
        }

        $abilities = $user->roles
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        if ($abilities === []) {
            $abilities = ['user'];
        }

        $plainTextToken = $user->createToken('mobile-api', $abilities)->plainTextToken;
        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success('Login successful.', [
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user, true),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles');

        return ApiResponse::success('Authenticated user.', [
            'user' => $this->serializeUser($user, true),
        ]);
    }

    public function verifyEmailOtp(VerifyEmailOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = (string) $validated['identifier'];
        $otp = (string) $validated['otp'];
        $user = $this->findUserByIdentifier($identifier);

        if (! $user || $user->email === null) {
            return ApiResponse::error('Invalid OTP request.', null, 422);
        }

        if ($user->email_verified_at !== null) {
            return ApiResponse::success('Email already verified.');
        }

        $verification = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $verification) {
            return ApiResponse::error('No pending OTP verification found.', null, 422);
        }

        if ($verification->expires_at->isPast()) {
            $verification->update([
                'status' => 'expired',
                'failure_reason' => 'OTP expired',
            ]);

            return ApiResponse::error('OTP expired.', null, 422);
        }

        if (! hash_equals((string) $verification->otp_hash, $this->hashOtp($otp))) {
            $verification->increment('attempt_count');

            return ApiResponse::error('Invalid OTP.', null, 422);
        }

        DB::transaction(function () use ($user, $verification): void {
            $verification->update([
                'status' => 'verified',
                'verified_at' => now(),
                'failure_reason' => null,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                ...$this->activeStatusAttributes(),
            ])->save();
        });

        return ApiResponse::success('Email verified.');
    }

    public function resendEmailOtp(ResendEmailOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = (string) $validated['identifier'];
        $user = $this->findUserByIdentifier($identifier);

        if (! $user || $user->email === null) {
            return ApiResponse::error('Invalid OTP request.', null, 422);
        }

        if ($user->email_verified_at !== null) {
            return ApiResponse::success('Email already verified.');
        }

        StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'failure_reason' => 'Superseded by resend',
            ]);

        ['otp' => $otp, 'expires_at' => $expiresAt] = $this->createPendingVerification(
            $user,
            $user->email,
            $request->ip()
        );

        if (! is_string($otp) || ! $expiresAt instanceof CarbonInterface) {
            return ApiResponse::error('Unable to resend OTP.', null, 500);
        }

        $this->sendEmailOtp($user->email, $otp, $expiresAt);

        return ApiResponse::success('OTP resent.');
    }

    private function assignDefaultRole(User $user): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'user'],
            [
                'name' => 'User',
                'description' => 'Default student account',
                'is_system' => true,
            ]
        );

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);
    }

    /**
     * @return array{otp: ?string, expires_at: CarbonInterface}
     */
    private function createPendingVerification(User $user, ?string $email, ?string $ipAddress): array
    {
        $expiresAt = now()->addMinutes((int) config('lnu.email_otp_expires_minutes', 10));

        if ($email !== null && $email !== '') {
            $otp = $this->generateOtp();

            StudentVerification::query()->create([
                'user_id' => $user->id,
                'verification_type' => 'email_otp',
                'token_hash' => null,
                'otp_hash' => $this->hashOtp($otp),
                'sent_to_email' => $email,
                'status' => 'pending',
                'attempt_count' => 0,
                'expires_at' => $expiresAt,
                'requested_ip' => $ipAddress,
                'failure_reason' => null,
            ]);

            return [
                'otp' => $otp,
                'expires_at' => $expiresAt,
            ];
        }

        StudentVerification::query()->create([
            'user_id' => $user->id,
            'verification_type' => 'email_link',
            'token_hash' => null,
            'otp_hash' => null,
            'sent_to_email' => null,
            'status' => 'pending',
            'attempt_count' => 0,
            'expires_at' => $expiresAt,
            'requested_ip' => $ipAddress,
            'failure_reason' => null,
        ]);

        return [
            'otp' => null,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{0: string, 1: ?string, 2: string}
     */
    private function splitName(string $name): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $parts = $normalized === '' ? [] : explode(' ', $normalized);

        if (count($parts) === 0) {
            return ['Student', null, 'User'];
        }

        if (count($parts) === 1) {
            return [$parts[0], null, $parts[0]];
        }

        $firstName = array_shift($parts);
        $lastName = array_pop($parts);
        $middleName = $parts === [] ? null : implode(' ', $parts);

        return [$firstName, $middleName, $lastName];
    }

    private function resolveStudentPrefix(string $studentId, int $prefixLength): ?string
    {
        $prefix = substr($studentId, 0, $prefixLength);

        if ($prefix === '') {
            return null;
        }

        $allowedPrefixes = config('lnu.allowed_student_id_prefixes', []);
        $normalizedPrefixes = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($allowedPrefixes) ? $allowedPrefixes : []
        )));

        if ($normalizedPrefixes !== [] && ! in_array($prefix, $normalizedPrefixes, true)) {
            return null;
        }

        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => $prefix],
            [
                'enrollment_year' => 2000 + (int) substr($prefix, 0, 2),
                'is_active' => true,
                'notes' => 'Auto-synced from registration validation rules.',
            ]
        );

        return $prefix;
    }

    private function hashOtp(string $otp): string
    {
        return hash('sha256', $otp);
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendEmailOtp(string $email, string $otp, CarbonInterface $expiresAt): void
    {
        Mail::to($email)->send(new EmailOtpMail($otp, $expiresAt));
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        $query = User::query()->with('roles');

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $query->where('email', $identifier)->first();
        }

        return $query->where('student_id', $identifier)->first();
    }

    /**
     * @return array<string, string>
     */
    private function pendingStatusAttributes(): array
    {
        if (Schema::hasColumn('users', 'status')) {
            return ['status' => 'pending_verification'];
        }

        return ['account_status' => 'pending_verification'];
    }

    /**
     * @return array<string, string>
     */
    private function activeStatusAttributes(): array
    {
        if (Schema::hasColumn('users', 'status')) {
            return ['status' => 'active'];
        }

        return ['account_status' => 'active'];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user, bool $withRoles): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->fullName(),
            'student_id' => $user->student_id,
            'email' => $user->email,
            'status' => $user->apiStatus(),
        ];

        if ($withRoles) {
            $payload['roles'] = $user->roles
                ->pluck('code')
                ->filter()
                ->values()
                ->all();
        }

        return $payload;
    }
}
