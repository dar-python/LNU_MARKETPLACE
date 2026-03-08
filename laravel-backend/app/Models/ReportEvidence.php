<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportEvidence extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'report_evidence';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'moderation_report_id',
        'uploaded_by_user_id',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'sha256_hash',
        'caption',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (ReportEvidence $reportEvidence): void {
            $path = $reportEvidence->file_path;

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

    public function moderationReport(): BelongsTo
    {
        return $this->belongsTo(ModerationReport::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
