<?php

namespace App\Filament\Widgets\Home;

use App\Enums\Permission;
use App\Filament\Resources\AccountDeletionRequests\AccountDeletionRequestResource;
use App\Filament\Resources\Farms\FarmResource;
use App\Filament\Resources\IdVerifications\IdVerificationResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Dashboard\HomeSummary;
use Filament\Widgets\Widget;

/**
 * COSEL's home screen: what is waiting, the partner farms, and recent activity.
 */
class CoselHome extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.home.cosel';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ManageAccounts->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $queue = HomeSummary::coselQueue();
        $viewer = auth()->user();

        return [
            'tiles' => [
                ['label' => 'ID checks waiting', 'count' => $queue['id_checks'], 'cta' => 'Review', 'url' => IdVerificationResource::getUrl(), 'warn' => $queue['id_checks'] > 0],
                ['label' => 'Deletion requests', 'count' => $queue['deletion_requests'], 'cta' => 'Open', 'url' => AccountDeletionRequestResource::getUrl(), 'warn' => $queue['deletion_requests'] > 0],
                ['label' => 'Open reports', 'count' => $queue['open_reports'], 'cta' => 'Moderate', 'url' => ReportResource::getUrl(), 'warn' => $queue['open_reports'] > 0],
                ['label' => 'New accounts this week', 'count' => $queue['new_accounts'], 'cta' => 'See accounts', 'url' => UserResource::getUrl(), 'warn' => false],
            ],
            'farms' => HomeSummary::partnerFarms(),
            'farmUrl' => fn ($farm): string => FarmResource::getUrl('edit', ['record' => $farm]),
            'farmsUrl' => FarmResource::getUrl(),
            'activity' => $viewer instanceof User ? HomeSummary::recentActivity($viewer) : collect(),
        ];
    }
}
