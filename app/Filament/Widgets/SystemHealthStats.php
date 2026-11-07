<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\SystemSetting;
use App\Support\AppVersion;
use App\Support\System\SystemHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * ICTD's health tiles. Shown on the System health page only.
 */
class SystemHealthStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ManageSystemSettings->value) ?? false;
    }

    protected function getStats(): array
    {
        $failed = SystemHealth::failedJobs();
        $backup = SystemHealth::lastBackupAt();
        $versions = SystemHealth::appVersions();
        $minimum = SystemSetting::minimumAppVersion();
        $outdated = collect($versions)
            ->filter(fn (int $people, string $version): bool => AppVersion::isOlder($version, $minimum))
            ->sum();

        return [
            Stat::make('Background jobs', $failed === 0 ? 'No failures' : "{$failed} failed")
                ->description(SystemHealth::pendingJobs().' waiting in the queue')
                ->color($failed === 0 ? 'success' : 'danger'),
            Stat::make('Storage used', SystemHealth::humanBytes(SystemHealth::storageBytes()))
                ->description('Photos, ID images and uploads'),
            Stat::make('Last backup', $backup?->diffForHumans() ?? 'Not recorded')
                ->description($backup?->toDayDateTimeString() ?? 'Runs daily from backup.sh')
                ->color($backup !== null && $backup->gt(now()->subDays(2)) ? 'success' : 'warning'),
            Stat::make('App versions (30 days)', $versions === [] ? 'No data yet' : collect($versions)->map(fn (int $n, string $v): string => "{$v}: {$n}")->implode(' · '))
                ->description($minimum === null ? 'No minimum version set' : "{$outdated} on a version below {$minimum}")
                ->color($outdated > 0 ? 'warning' : 'gray'),
        ];
    }
}
