<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredEvidence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostReport extends Model
{
    use DeletesStoredEvidence, HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_RESOLVED,
        self::STATUS_REJECTED,
    ];

    /**
     * @var list<string>
     */
    public const REASON_CATEGORIES = [
        'scam',
        'inappropriate_content',
        'prohibited_item',
        'harassment',
        'impersonation',
        'spam',
        'other',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'listing_id',
        'reporter_user_id',
        'reason_category',
        'description',
        'status',
        'evidence_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason_category' => 'string',
            'status' => 'string',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PostReportStatusHistory::class, 'post_report_id');
    }
}
