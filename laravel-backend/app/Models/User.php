<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'student_id_prefix',
        'email',
        'password',
        'role',
        'first_name',
        'last_name',
        'middle_name',
        'profile_photo_path',
        'status',
        'account_status',
        'is_approved',
        'approved_at',
        'approved_by_user_id',
        'declined_at',
        'declined_by_user_id',
        'declined_reason',
        'is_disabled',
        'disabled_at',
        'email_verified_at',
        'suspended_until',
        'suspended_reason',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_status' => 'string',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'approved_by_user_id' => 'integer',
            'declined_at' => 'datetime',
            'declined_by_user_id' => 'integer',
            'is_disabled' => 'boolean',
            'disabled_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'suspended_until' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function studentIdPrefix(): BelongsTo
    {
        return $this->belongsTo(StudentIdPrefix::class, 'student_id_prefix', 'prefix');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function declinedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by_user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['assigned_by_user_id', 'assigned_at'])
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function rolesAssigned(): HasMany
    {
        return $this->hasMany(UserRole::class, 'assigned_by_user_id');
    }

    public function studentVerifications(): HasMany
    {
        return $this->hasMany(StudentVerification::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function approvedListings(): HasMany
    {
        return $this->hasMany(Listing::class, 'approved_by_user_id');
    }

    public function listingImagesUploaded(): HasMany
    {
        return $this->hasMany(ListingImage::class, 'uploaded_by_user_id');
    }

    public function favoriteListings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites')
            ->withTimestamps();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function sentInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'sender_user_id');
    }

    public function receivedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'recipient_user_id');
    }

    public function moderationReportsFiled(): HasMany
    {
        return $this->hasMany(ModerationReport::class, 'reporter_user_id');
    }

    public function moderationReportsAssigned(): HasMany
    {
        return $this->hasMany(ModerationReport::class, 'assigned_admin_user_id');
    }

    public function moderationReportsAsTargetUser(): HasMany
    {
        return $this->hasMany(ModerationReport::class, 'target_user_id');
    }

    public function reportEvidenceUploads(): HasMany
    {
        return $this->hasMany(ReportEvidence::class, 'uploaded_by_user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_user_id');
    }

    public function marketplaceNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function exportLogs(): HasMany
    {
        return $this->hasMany(ExportLog::class, 'requested_by_user_id');
    }

    public function apiStatus(): string
    {
        $status = $this->attributes['status'] ?? $this->account_status ?? null;

        return match ((string) $status) {
            'approved', 'active' => 'approved',
            'pending', 'pending_verification' => 'pending',
            'suspended', 'deactivated', 'disabled' => 'suspended',
            default => 'pending',
        };
    }

    public function isPendingApproval(): bool
    {
        return $this->apiStatus() === 'pending';
    }

    public function isBlockedFromLogin(): bool
    {
        return $this->apiStatus() === 'suspended';
    }

    public function fullName(): string
    {
        if (
            $this->middle_name === null
            && is_string($this->first_name)
            && is_string($this->last_name)
            && $this->first_name === $this->last_name
        ) {
            return $this->first_name;
        }

        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ], fn ($value) => is_string($value) && $value !== '');

        return implode(' ', $parts);
    }
}
