<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DECLINED = 'declined';

    /**
     * @var list<string>
     */
    public const DECISION_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_DECLINED,
    ];

    /**
     * @var list<string>
     */
    public const PREFERRED_CONTACT_METHODS = [
        'in_app',
        'email',
        'phone',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'listing_id',
        'sender_user_id',
        'recipient_user_id',
        'subject',
        'message',
        'preferred_contact_method',
        'status',
        'proof_image_path',
        'completed_at',
        'seller_confirmed_at',
        'buyer_confirmed_at',
        'decided_at',
        'decided_by',
        'inquiry_status',
        'responded_at',
        'response_note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_contact_method' => 'string',
            'status' => 'string',
            'proof_image_path' => 'string',
            'completed_at' => 'datetime',
            'seller_confirmed_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'decided_at' => 'datetime',
            'decided_by' => 'integer',
            'inquiry_status' => 'string',
            'responded_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeDecidedBy(int $userId): bool
    {
        $listingOwnerId = $this->listing?->user_id;

        return $userId === (int) $this->recipient_user_id
            && $listingOwnerId !== null
            && $userId === (int) $listingOwnerId;
    }
}
