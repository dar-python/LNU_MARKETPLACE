<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ListingImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'listing_id',
        'image_path',
        'sort_order',
        'is_primary',
        'uploaded_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (ListingImage $listingImage): void {
            $path = $listingImage->image_path;

            if (! is_string($path) || $path === '') {
                return;
            }

            $deleteFile = static function () use ($path): void {
                $disk = Storage::disk('public');
                $disk->delete($path);

                $directory = dirname($path);
                if ($directory === '.' || $directory === '') {
                    return;
                }

                if ($disk->allFiles($directory) === [] && $disk->allDirectories($directory) === []) {
                    $disk->deleteDirectory($directory);
                }
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($deleteFile);

                return;
            }

            $deleteFile();
        });
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}

