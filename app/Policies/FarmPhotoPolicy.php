<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Farm;
use App\Models\FarmPhoto;
use App\Models\User;

/**
 * Farm gallery photos. Holding ManageOwnFarmProfile lets a Content Editor
 * write photos on their own farm. ManageFarms (Super Admin) reaches every farm.
 */
class FarmPhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManagePhotos($user);
    }

    public function view(User $user, FarmPhoto $farmPhoto): bool
    {
        return $this->reaches($user, $farmPhoto->farm_id);
    }

    public function create(User $user): bool
    {
        return $this->canManagePhotos($user);
    }

    /**
     * For the create path and the Filament relation manager, where the farm is
     * known from the parent record but no photo exists yet.
     */
    public function createForFarm(User $user, Farm $farm): bool
    {
        return $this->reaches($user, $farm->getKey());
    }

    public function update(User $user, FarmPhoto $farmPhoto): bool
    {
        return $this->reaches($user, $farmPhoto->farm_id);
    }

    public function delete(User $user, FarmPhoto $farmPhoto): bool
    {
        return $this->reaches($user, $farmPhoto->farm_id);
    }

    private function canManagePhotos(User $user): bool
    {
        return $user->can(Permission::ManageFarms->value)
            || $user->can(Permission::ManageOwnFarmProfile->value);
    }

    private function reaches(User $user, int|string|null $farmId): bool
    {
        if (! $this->canManagePhotos($user)) {
            return false;
        }

        if ($user->can(Permission::ManageFarms->value)) {
            return true;
        }

        return $farmId !== null
            && $user->farm_id !== null
            && (int) $user->farm_id === (int) $farmId;
    }
}
