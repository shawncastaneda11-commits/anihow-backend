<?php

namespace App\Support\System;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The numbers on ICTD's System health page.
 */
final class SystemHealth
{
    /** Written by scripts/vps/backup.sh after each successful database backup. */
    public const BACKUP_MARKER = 'app/last-backup.txt';

    public static function failedJobs(): int
    {
        return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }

    public static function pendingJobs(): int
    {
        return Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
    }

    /** Bytes used by uploaded photos and files. Cached for ten minutes. */
    public static function storageBytes(): int
    {
        return (int) Cache::remember('system_health_storage_bytes', 600, function (): int {
            $root = storage_path('app');

            if (! is_dir($root)) {
                return 0;
            }

            $total = 0;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $total += $file->getSize();
                }
            }

            return $total;
        });
    }

    public static function lastBackupAt(): ?Carbon
    {
        $path = storage_path(self::BACKUP_MARKER);

        if (! is_file($path)) {
            return null;
        }

        $stamp = trim((string) file_get_contents($path));

        try {
            return $stamp === '' ? null : Carbon::parse($stamp);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * App versions people opened AniHow with in the last 30 days.
     *
     * @return array<string, int> version => people
     */
    public static function appVersions(): array
    {
        return User::query()
            ->whereNotNull('last_app_version')
            ->where('last_app_seen_at', '>=', now()->subDays(30))
            ->selectRaw('last_app_version, count(*) as people')
            ->groupBy('last_app_version')
            ->orderByDesc('last_app_version')
            ->pluck('people', 'last_app_version')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
