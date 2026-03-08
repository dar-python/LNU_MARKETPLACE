<?php

namespace App\Models;

use App\Models\Concerns\DeletesStoredEvidence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserReport extends Model
{
    use DeletesStoredEvidence, HasFactory;

    public const STATUS_SUBMITTED = PostReport::STATUS_SUBMITTED;

    /**
     * @var list<string>
     */
    public const STATUSES = PostReport::STATUSES;

    /**
     * @var list<string>
     */
    public const REASON_CATEGORIES = PostReport::REASON_CATEGORIES;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reported_user_id',
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

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(UserReportStatusHistory::class, 'user_report_id');
    }
}
