<?php

namespace App\Actions\Exports\Concerns;

use App\Enums\Permission;
use App\Models\User;

/**
 * Decides which farm an analytics report may cover. The Super Admin may pick
 * any farm or all of them; a Content Editor only ever gets their own farm.
 */
trait ResolvesExportFarm
{
    private function resolvedFarmId(User $actor, ?int $farmId): ?int
    {
        if ($actor->can(Permission::GenerateExports->value)) {
            return $farmId;
        }

        if ($actor->can(Permission::ExportFarmAnalytics->value) && $actor->farm_id !== null) {
            if ($farmId !== null && (int) $farmId !== (int) $actor->farm_id) {
                abort(403);
            }

            return (int) $actor->farm_id;
        }

        abort(403);
    }
}
