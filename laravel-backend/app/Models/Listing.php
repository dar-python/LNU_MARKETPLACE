<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'price',
        'item_condition',
        'quantity',
        'is_negotiable',
        'meetup_arrangement',
        'service_type',
        'service_mode',
        'listing_status',
        'is_flagged',
        'moderation_note',
        'approved_by_user_id',
        'approved_at',
        'sold_at',
        'expires_at',
        'view_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'item_condition' => 'string',
            'quantity' => 'integer',
            'is_negotiable' => 'boolean',
            'listing_status' => 'string',
            'is_flagged' => 'boolean',
            'approved_at' => 'datetime',
            'sold_at' => 'datetime',
            'expires_at' => 'datetime',
            'view_count' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function listingImages(): HasMany
    {
        return $this->hasMany(ListingImage::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoredBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->favoredBy();
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function postReports(): HasMany
    {
        return $this->hasMany(PostReport::class, 'listing_id');
    }

    public function moderationReports(): HasMany
    {
        return $this->postReports();
    }
}

