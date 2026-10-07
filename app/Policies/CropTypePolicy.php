<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CropType;
use App\Models\User;

/**
 * Each farm keeps its own crop types. A Content Editor writes only the rows
 * that belong to their farm. The Super Admin writes every farm's rows.
 */
class CropTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CropType $cropType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCropTypes->value)
            || ($user->can(Permission::ManageOwnFarmProfile->value) && $user->farm_id !== null);
    }

    public function update(User $user, CropType $cropType): bool
    {
        return $this->manages($user, $cropType);
    }

    public function delete(User $user, CropType $cropType): bool
    {
        return $this->manages($user, $cropType);
    }

    /**
     * Floor price and maximum tawad. The Super Admin can set them on any
     * crop type. A Content Editor can set them on their own farm's crop types.
     */
    public function setPricing(User $user, ?CropType $cropType = null): bool
    {
        if ($user->can(Permission::SetCropPricing->value)) {
            return true;
        }

        if (! $user->can(Permission::ManageOwnFarmProfile->value) || $user->farm_id === null) {
            return false;
        }

        if ($cropType === null || $cropType->farm_id === null) {
            return $cropType === null;
        }

        return (int) $cropType->farm_id === (int) $user->farm_id;
    }

    private function manages(User $user, CropType $cropType): bool
    {
        if ($user->can(Permission::ManageCropTypes->value)) {
            return true;
        }

        return $user->can(Permission::ManageOwnFarmProfile->value)
            && $cropType->farm_id !== null
            && (int) $cropType->farm_id === (int) $user->farm_id;
    }
}
