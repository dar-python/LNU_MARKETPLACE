<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait DeletesStoredEvidence
{
    protected static function bootDeletesStoredEvidence(): void
    {
        static::deleted(function (Model $report): void {
            $path = $report->getAttribute('evidence_path');

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
}
