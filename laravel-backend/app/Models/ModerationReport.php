<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModerationReport extends Model
{
    use HasFactory;

    public const TARGET_TYPE_LISTING = 'listing';

    public const TARGET_TYPE_USER = 'user';

    /**
     * @var list<string>
     */
    public const TARGET_TYPES = [
        self::TARGET_TYPE_LISTING,
        self::TARGET_TYPE_USER,
    ];

    public const STATUS_PENDING = 'pending';

    public const PRIORITY_MEDIUM = 'medium';

    public const RESOLUTION_ACTION_NONE = 'none';

    /**
     * @var list<string>
     */
    public const REPORT_CATEGORIES = [
        'spam',
        'fraud',
        'prohibited_item',
        'harassment',
        'fake_listing',
        'other',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reporter_user_id',
        'target_type',
        'target_listing_id',
        'target_user_id',
        'report_category',
        'description',
        'status',
        'priority',
        'assigned_admin_user_id',
        'resolution_action',
        'resolution_notes',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => 'string',
            'report_category' => 'string',
            'status' => 'string',
            'priority' => 'string',
            'resolution_action' => 'string',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ModerationReport $report): void {
            $report->evidence()->get()->each->delete();
        });
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function targetListing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'target_listing_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_user_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReportEvidence::class);
    }
}
