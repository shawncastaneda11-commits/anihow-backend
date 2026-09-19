<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\Permission;

/**
 * A farmer-seller sees their own figures in the app, not here. The panel is
 * the Super Admin's system-wide view and the Content Editor's farm view.
 */
trait ScopedAnalytics
{
    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can(Permission::ViewSystemAnalytics->value)
            || $user->can(Permission::ViewFarmAnalytics->value);
    }
}
