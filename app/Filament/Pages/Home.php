<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Widgets\Home\CoselHome;
use App\Filament\Widgets\Home\EditorHome;
use App\Filament\Widgets\Home\IctdHome;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * The first screen after sign-in. Each office sees its own home: COSEL what
 * is waiting, ICTD how the system is doing, a Content Editor what the farm's
 * sellers need. Analytics moved to its own page (Dashboard).
 */
class Home extends BaseDashboard
{
    protected static string $routePath = '/';

    protected static ?string $title = 'Home';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [CoselHome::class, IctdHome::class, EditorHome::class];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getTitle(): string|Htmlable
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return 'Home';
        }

        if ($user->isContentEditor()) {
            return $user->farm?->name ?? 'Home';
        }

        if ($user->can(Permission::ManageAccounts->value)) {
            return 'COSEL home';
        }

        if ($user->can(Permission::ManageAdminAccounts->value)) {
            return 'ICTD home';
        }

        return 'Home';
    }
}
