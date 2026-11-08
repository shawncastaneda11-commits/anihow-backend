<?php

namespace App\Filament\Widgets\Home;

use App\Enums\Permission;
use App\Filament\Pages\SystemHealthPage;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Dashboard\HomeSummary;
use Filament\Widgets\Widget;

/**
 * ICTD's home screen: system health, security and app settings, and recent activity.
 */
class IctdHome extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.home.ictd';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ManageAdminAccounts->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $overview = HomeSummary::ictdOverview();
        $viewer = auth()->user();

        return [
            'overview' => $overview,
            'tiles' => [
                ['label' => 'Background jobs', 'value' => $overview['failed_jobs'].' failed', 'note' => $overview['pending_jobs'].' waiting', 'warn' => $overview['failed_jobs'] > 0],
                ['label' => 'Storage used', 'value' => $overview['storage'], 'note' => 'Photos and uploads', 'warn' => false],
                ['label' => 'Last backup', 'value' => $overview['last_backup'] ?? 'Not recorded', 'note' => 'From the nightly backup', 'warn' => $overview['last_backup'] === null],
                ['label' => 'Apps on old versions', 'value' => (string) $overview['old_apps'], 'note' => $overview['min_version'] ? 'Below '.$overview['min_version'] : 'No minimum set', 'warn' => $overview['old_apps'] > 0],
            ],
            'healthUrl' => SystemHealthPage::getUrl(),
            'createAdminUrl' => UserResource::getUrl('create'),
            'activityUrl' => ActivityLogResource::getUrl(),
            'activity' => $viewer instanceof User ? HomeSummary::recentActivity($viewer) : collect(),
        ];
    }
}
