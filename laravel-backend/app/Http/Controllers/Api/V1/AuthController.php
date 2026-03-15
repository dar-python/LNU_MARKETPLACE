<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResendEmailOtpRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyEmailOtpRequest;
use App\Mail\EmailOtpMail;
use App\Mail\PasswordResetOtpMail;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\StudentVerification;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

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
        $verificationId = null;

        $user = DB::transaction(function () use ($request, $validated, $studentId, $studentIdPrefix, $firstName, $middleName, $lastName, $email, &$otp, &$otpExpiresAt, &$verificationId): User {
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

            ['otp' => $otp, 'expires_at' => $otpExpiresAt, 'verification_id' => $verificationId] = $this->createPendingVerification(
                $user,
                $email,
                $request->ip()
            );

            $this->assignDefaultRole($user);

            return $user->load('roles');
        });

        if ($email !== null && is_string($otp) && $otpExpiresAt instanceof CarbonInterface) {
            $this->sendEmailOtp($email, $otp, $otpExpiresAt);

            $this->logActivity(
                $request,
                'auth.email_otp_sent',
                $user,
                'Email OTP sent after registration.',
                [
                    'verification_id' => $verificationId,
                    'expires_at' => $otpExpiresAt->toIso8601String(),
                ],
                $user
            );
        }

        $this->logActivity(
            $request,
            'auth.registered',
            $user,
            'User registration completed.',
            [
                'requires_email_verification' => $email !== null,
            ],
            $user
        );

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
        $email = isset($validated['email']) && is_string($validated['email']) && $validated['email'] !== ''
            ? $validated['email']
            : null;
        $studentId = isset($validated['student_id']) && is_string($validated['student_id']) && $validated['student_id'] !== ''
            ? $validated['student_id']
            : null;
        $password = (string) $validated['password'];
        $identifier = $email ?? $studentId ?? '';

        $user = $this->findUserForLogin($email, $studentId);

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->logActivity(
                $request,
                'auth.login_failed',
                $user,
                'Login failed due to invalid credentials.',
                [
                    'identifier' => $identifier,
                    'reason' => 'invalid_credentials',
                ],
                $user
            );

            return ApiResponse::error('Invalid credentials.', null, 401);
        }

        if ($user->isBlockedFromLogin()) {
            $this->logActivity(
                $request,
                'auth.login_blocked',
                $user,
                'Login blocked because account is suspended.',
                [
                    'reason' => 'account_suspended',
                ],
                $user
            );

            return ApiResponse::error('Account suspended.', [
                'code' => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        if ($user->email !== null && $user->email_verified_at === null) {
            $this->logActivity(
                $request,
                'auth.login_blocked',
                $user,
                'Login blocked because email is not verified.',
                [
                    'reason' => 'email_not_verified',
                ],
                $user
            );

            return ApiResponse::error('Email not verified yet.', [
                'code' => 'EMAIL_NOT_VERIFIED',
                'email' => $user->email,
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

        $this->logActivity(
            $request,
            'auth.login_success',
            $user,
            'User logged in successfully.',
            [
                'abilities' => $abilities,
            ],
            $user
        );

        return ApiResponse::success('Login successful.', [
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->serializeUser($user, true),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();
        $bearerToken = $request->bearerToken();

        if (! $currentToken && is_string($bearerToken) && $bearerToken !== '') {
            $resolvedToken = PersonalAccessToken::findToken($bearerToken);

            if (
                $resolvedToken !== null
                && $resolvedToken->tokenable_id === $user?->id
                && $resolvedToken->tokenable_type === $user?->getMorphClass()
            ) {
                $currentToken = $resolvedToken;
            }
        }

        $tokenId = $currentToken?->id;
        $currentToken?->delete();

        $this->logActivity(
            $request,
            'auth.logout',
            $user,
            'User logged out.',
            [
                'token_id' => $tokenId,
            ],
            $user
        );

        return ApiResponse::success('Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('roles');

        return ApiResponse::success('Authenticated user.', [
            'user' => $this->serializeUser($user, true),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_number' => ['nullable', 'string', 'max:30'],
            'program' => ['nullable', 'string', 'max:100'],
            'year_level' => ['nullable', 'string', 'max:50'],
            'organization' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_contact_public' => ['sometimes', 'boolean'],
            'is_program_public' => ['sometimes', 'boolean'],
            'is_year_level_public' => ['sometimes', 'boolean'],
            'is_organization_public' => ['sometimes', 'boolean'],
            'is_section_public' => ['sometimes', 'boolean'],
            'is_bio_public' => ['sometimes', 'boolean'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user()->loadMissing('roles');
        $profilePicture = $request->file('profile_picture');
        $storedPath = null;
        $previousPath = $this->currentProfilePicturePath($user);

        try {
            if ($profilePicture instanceof UploadedFile) {
                $storedPath = $profilePicture->store('profile-pictures/'.$user->id, 'public');
            }

            DB::transaction(function () use ($user, $validated, $storedPath): void {
                $updates = [];

                foreach (['contact_number', 'program', 'year_level', 'organization', 'section', 'bio'] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $updates[$field] = $this->nullableString($validated[$field]);
                    }
                }

                foreach ([
                    'is_contact_public',
                    'is_program_public',
                    'is_year_level_public',
                    'is_organization_public',
                    'is_section_public',
                    'is_bio_public',
                ] as $field) {
                    if (array_key_exists($field, $validated)) {
                        $updates[$field] = (bool) $validated[$field];
                    }
                }

                if (is_string($storedPath) && $storedPath !== '') {
                    $updates['profile_picture_path'] = $storedPath;
                }

                if ($updates !== []) {
                    $user->forceFill($updates)->save();
                }
            });
        } catch (\Throwable $throwable) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('public')->delete($storedPath);
            }

            throw $throwable;
        }

        if (
            is_string($storedPath)
            && $storedPath !== ''
            && is_string($previousPath)
            && $previousPath !== ''
            && $previousPath !== $storedPath
        ) {
            Storage::disk('public')->delete($previousPath);
        }

        return ApiResponse::success('Profile updated successfully.', [
            'user' => $this->serializeUser($user->refresh()->loadMissing('roles'), true),
        ]);
    }

    public function verifyEmailOtp(VerifyEmailOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = (string) $validated['identifier'];
        $otp = (string) $validated['otp'];
        $user = $this->findUserByIdentifier($identifier);

        if (! $user || $user->email === null) {
            $this->logActivity(
                $request,
                'auth.email_otp_verify_failed',
                null,
                'OTP verification failed due to invalid identifier.',
                [
                    'identifier' => $identifier,
                    'reason' => 'invalid_request',
                ]
            );

            return ApiResponse::error('Invalid OTP request.', null, 422);
        }

        if ($user->email_verified_at !== null) {
            $this->logActivity(
                $request,
                'auth.email_otp_verify_skipped',
                $user,
                'OTP verification skipped because email is already verified.',
                [
                    'reason' => 'already_verified',
                ],
                $user
            );

            return ApiResponse::success('Email already verified.');
        }

        $verification = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $verification) {
            $this->logActivity(
                $request,
                'auth.email_otp_verify_failed',
                $user,
                'OTP verification failed because no pending OTP was found.',
                [
                    'reason' => 'no_pending_otp',
                ],
                $user
            );

            return ApiResponse::error('No pending OTP verification found.', null, 422);
        }

        if ($verification->expires_at->isPast()) {
            $verification->update([
                'status' => 'expired',
                'failure_reason' => 'OTP expired',
            ]);

            $this->logActivity(
                $request,
                'auth.email_otp_verify_failed',
                $user,
                'OTP verification failed because OTP expired.',
                [
                    'verification_id' => $verification->id,
                    'reason' => 'otp_expired',
                ],
                $user
            );

            return ApiResponse::error('OTP expired.', null, 422);
        }

        if (! hash_equals((string) $verification->otp_hash, $this->hashOtp($otp))) {
            $nextAttemptCount = $verification->attempt_count + 1;
            $verification->increment('attempt_count');

            $this->logActivity(
                $request,
                'auth.email_otp_verify_failed',
                $user,
                'OTP verification failed because OTP is invalid.',
                [
                    'verification_id' => $verification->id,
                    'reason' => 'invalid_otp',
                    'attempt_count' => $nextAttemptCount,
                ],
                $user
            );

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

        $this->logActivity(
            $request,
            'auth.email_otp_verified',
            $user,
            'Email OTP verified successfully.',
            [
                'verification_id' => $verification->id,
            ],
            $user
        );

        return ApiResponse::success('Email verified.');
    }

    public function resendEmailOtp(ResendEmailOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $identifier = (string) $validated['identifier'];
        $user = $this->findUserByIdentifier($identifier);

        if (! $user || $user->email === null) {
            $this->logActivity(
                $request,
                'auth.email_otp_resend_failed',
                null,
                'OTP resend failed due to invalid identifier.',
                [
                    'identifier' => $identifier,
                    'reason' => 'invalid_request',
                ]
            );

            return ApiResponse::error('Invalid OTP request.', null, 422);
        }

        if ($user->email_verified_at !== null) {
            $this->logActivity(
                $request,
                'auth.email_otp_resend_skipped',
                $user,
                'OTP resend skipped because email is already verified.',
                [
                    'reason' => 'already_verified',
                ],
                $user
            );

            return ApiResponse::success('Email already verified.');
        }

        $expiredCount = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'email_otp')
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'failure_reason' => 'Superseded by resend',
            ]);

        ['otp' => $otp, 'expires_at' => $expiresAt, 'verification_id' => $verificationId] = $this->createPendingVerification(
            $user,
            $user->email,
            $request->ip()
        );

        if (! is_string($otp) || ! $expiresAt instanceof CarbonInterface) {
            $this->logActivity(
                $request,
                'auth.email_otp_resend_failed',
                $user,
                'OTP resend failed because OTP generation did not return valid data.',
                [
                    'reason' => 'otp_generation_failed',
                ],
                $user
            );

            return ApiResponse::error('Unable to resend OTP.', null, 500);
        }

        $this->sendEmailOtp($user->email, $otp, $expiresAt);

        $this->logActivity(
            $request,
            'auth.email_otp_resent',
            $user,
            'Email OTP resent successfully.',
            [
                'expired_pending_records' => $expiredCount,
                'verification_id' => $verificationId,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            $user
        );

        return ApiResponse::success('OTP resent.');
    }

    // ── Forgot Password ───────────────────────────────────────────────────────

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = (string) $validated['email'];
        $user = $this->findUserByEmail($email);

        // Always return success to prevent user enumeration
        if (! $user || $user->email === null) {
            return ApiResponse::success('If that account exists, an OTP has been sent to the registered email.');
        }

        // Expire any existing pending password reset OTPs
        StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'password_reset_otp')
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'failure_reason' => 'Superseded by new request',
            ]);

        $expiresAt = now()->addMinutes((int) config('lnu.email_otp_expires_minutes', 10));
        $otp = $this->generateOtp();

        StudentVerification::query()->create([
            'user_id'           => $user->id,
            'verification_type' => 'password_reset_otp',
            'token_hash'        => null,
            'otp_hash'          => $this->hashOtp($otp),
            'sent_to_email'     => $user->email,
            'status'            => 'pending',
            'attempt_count'     => 0,
            'expires_at'        => $expiresAt,
            'requested_ip'      => $request->ip(),
            'failure_reason'    => null,
        ]);

        Mail::to($user->email)->send(new PasswordResetOtpMail($otp, $expiresAt));

        $this->logActivity(
            $request,
            'auth.password_reset_otp_sent',
            $user,
            'Password reset OTP sent.',
            ['expires_at' => $expiresAt->toIso8601String()],
            $user
        );

        return ApiResponse::success('If that account exists, an OTP has been sent to the registered email.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = (string) $validated['email'];
        $otp = (string) $validated['otp'];
        $password = (string) $validated['password'];

        $user = $this->findUserByEmail($email);

        if (! $user) {
            return ApiResponse::error('Invalid request.', null, 422);
        }

        $verification = StudentVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'password_reset_otp')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $verification) {
            return ApiResponse::error('No pending password reset OTP found. Please request a new one.', null, 422);
        }

        if ($verification->expires_at->isPast()) {
            $verification->update([
                'status'         => 'expired',
                'failure_reason' => 'OTP expired',
            ]);

            $this->logActivity(
                $request,
                'auth.password_reset_failed',
                $user,
                'Password reset failed because OTP expired.',
                ['verification_id' => $verification->id, 'reason' => 'otp_expired'],
                $user
            );

            return ApiResponse::error('OTP expired. Please request a new one.', null, 422);
        }

        if (! hash_equals((string) $verification->otp_hash, $this->hashOtp($otp))) {
            $verification->increment('attempt_count');

            $this->logActivity(
                $request,
                'auth.password_reset_failed',
                $user,
                'Password reset failed because OTP is invalid.',
                [
                    'verification_id' => $verification->id,
                    'reason'          => 'invalid_otp',
                    'attempt_count'   => $verification->attempt_count + 1,
                ],
                $user
            );

            return ApiResponse::error('Invalid OTP.', null, 422);
        }

        DB::transaction(function () use ($user, $verification, $password): void {
            $verification->update([
                'status'      => 'verified',
                'verified_at' => now(),
                'failure_reason' => null,
            ]);

            $user->forceFill(['password' => Hash::make($password)])->save();

            // Revoke all existing tokens so old sessions are invalidated
            $user->tokens()->delete();
        });

        $this->logActivity(
            $request,
            'auth.password_reset_success',
            $user,
            'Password reset successfully.',
            ['verification_id' => $verification->id],
            $user
        );

        return ApiResponse::success('Password reset successfully. Please log in with your new password.');
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

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
     * @return array{otp: ?string, expires_at: CarbonInterface, verification_id: int}
     */
    private function createPendingVerification(User $user, ?string $email, ?string $ipAddress): array
    {
        $expiresAt = now()->addMinutes((int) config('lnu.email_otp_expires_minutes', 10));

        if ($email !== null && $email !== '') {
            $otp = $this->generateOtp();

            $verification = StudentVerification::query()->create([
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
                'verification_id' => $verification->id,
            ];
        }

        $verification = StudentVerification::query()->create([
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
            'verification_id' => $verification->id,
        ];
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function logActivity(
        Request $request,
        string $actionType,
        ?User $actor,
        ?string $description = null,
        ?array $metadata = null,
        ?User $subject = null
    ): void {
        try {
            ActivityLog::query()->create([
                'actor_user_id' => $actor?->id,
                'action_type' => $actionType,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->id,
                'description' => $description,
                'metadata' => $metadata,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);
        }
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

    private function findUserForLogin(?string $email, ?string $studentId): ?User
    {
        $query = User::query()->with('roles');

        if (is_string($email) && $email !== '') {
            return $query->where('email', $email)->first();
        }

        if (is_string($studentId) && $studentId !== '') {
            return $query->where('student_id', $studentId)->first();
        }

        return null;
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->with('roles')
            ->where('email', $email)
            ->first();
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
            'contact_number' => $user->contact_number,
            'program' => $user->program,
            'year_level' => $user->year_level,
            'organization' => $user->organization,
            'section' => $user->section,
            'bio' => $user->bio,
            'is_contact_public' => (bool) $user->is_contact_public,
            'is_program_public' => (bool) $user->is_program_public,
            'is_year_level_public' => (bool) $user->is_year_level_public,
            'is_organization_public' => (bool) $user->is_organization_public,
            'is_section_public' => (bool) $user->is_section_public,
            'is_bio_public' => (bool) $user->is_bio_public,
            'profile_picture_path' => $this->currentProfilePicturePath($user),
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function currentProfilePicturePath(User $user): ?string
    {
        $profilePicturePath = $user->profile_picture_path;

        if (is_string($profilePicturePath) && $profilePicturePath !== '') {
            return $profilePicturePath;
        }

        $legacyProfilePhotoPath = $user->profile_photo_path;

        return is_string($legacyProfilePhotoPath) && $legacyProfilePhotoPath !== ''
            ? $legacyProfilePhotoPath
            : null;
    }
}
