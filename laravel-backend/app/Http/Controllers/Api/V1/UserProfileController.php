<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class UserProfileController extends Controller
{
    /**
     * @var array<string, bool>
     */
    private const PROFILE_PRIVACY_FIELDS = [
        'is_contact_public' => false,
        'is_program_public' => true,
        'is_year_level_public' => true,
        'is_organization_public' => true,
        'is_section_public' => true,
        'is_bio_public' => true,
    ];

    public function show(User $user): JsonResponse
    {
        if ($user->apiStatus() !== 'approved') {
            return ApiResponse::error('User not found.', null, 404);
        }

        if (Schema::hasColumn('users', 'is_disabled') && (bool) $user->is_disabled) {
            return ApiResponse::error('User not found.', null, 404);
        }

        return ApiResponse::success('User profile retrieved successfully.', [
            'user' => $this->serializePublicProfile($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublicProfile(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->fullName(),
            'contact_number' => $this->publicValue($this->profilePrivacyValue($user, 'is_contact_public'), $user->contact_number),
            'program' => $this->publicValue($this->profilePrivacyValue($user, 'is_program_public'), $user->program),
            'year_level' => $this->publicValue($this->profilePrivacyValue($user, 'is_year_level_public'), $user->year_level),
            'organization' => $this->publicValue($this->profilePrivacyValue($user, 'is_organization_public'), $user->organization),
            'section' => $this->publicValue($this->profilePrivacyValue($user, 'is_section_public'), $user->section),
            'bio' => $this->publicValue($this->profilePrivacyValue($user, 'is_bio_public'), $user->bio),
            'profile_picture_path' => $this->currentProfilePicturePath($user),
        ];
    }

    private function publicValue(bool $isPublic, mixed $value): ?string
    {
        if (! $isPublic || ! is_string($value)) {
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

    private function profilePrivacyValue(User $user, string $field): bool
    {
        $fallback = self::PROFILE_PRIVACY_FIELDS[$field] ?? true;

        if (! Schema::hasColumn('users', $field)) {
            return $fallback;
        }

        return (bool) $user->{$field};
    }
}
