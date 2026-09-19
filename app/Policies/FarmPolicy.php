<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Farm;
use App\Models\User;

class FarmPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageFarms->value)
            || $user->can(Permission::ManageOwnFarmProfile->value);
    }

    public function view(User $user, Farm $farm): bool
    {
        if ($user->can(Permission::ManageFarms->value)) {
            return true;
        }

        return $this->scopedToFarm($user, $farm->id)
            && $user->can(Permission::ManageOwnFarmProfile->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageFarms->value);
    }

    /**
     * A Content Editor maintains their own farm's profile: description,
     * contact, pickup point, photos. They cannot reach another farm.
     */
    public function update(User $user, Farm $farm): bool
    {
        if ($user->can(Permission::ManageFarms->value)) {
            return true;
        }

        return $this->scopedToFarm($user, $farm->id)
            && $user->can(Permission::ManageOwnFarmProfile->value);
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $user->can(Permission::ManageFarms->value);
    }

    private function scopedToFarm(User $user, int $farmId): bool
    {
        return $user->farm_id !== null && $user->farm_id === $farmId;
    }
}
