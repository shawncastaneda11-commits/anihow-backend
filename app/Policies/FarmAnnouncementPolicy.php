<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FarmAnnouncement;
use App\Models\User;

/**
 * Content Editors write announcements for their own farm. Super Admins hold
 * the same permission plus ManageFarms, so they can post for any farm.
 * Farmer-sellers only read their farm's active announcements in the app.
 */
class FarmAnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOwnFarmAnnouncements->value)
            || $this->readsOwnFarmAnnouncements($user);
    }

    public function view(User $user, FarmAnnouncement $farmAnnouncement): bool
    {
        if ($this->managesAnyFarm($user)) {
            return true;
        }

        return $this->scopedToFarm($user, $farmAnnouncement->farm_id)
            && ($user->can(Permission::ManageOwnFarmAnnouncements->value)
                || $this->readsOwnFarmAnnouncements($user));
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnFarmAnnouncements->value)
            && ($this->managesAnyFarm($user) || $user->farm_id !== null);
    }

    public function update(User $user, FarmAnnouncement $farmAnnouncement): bool
    {
        return $this->writesFarmOf($user, $farmAnnouncement);
    }

    public function delete(User $user, FarmAnnouncement $farmAnnouncement): bool
    {
        return $this->writesFarmOf($user, $farmAnnouncement);
    }

    private function writesFarmOf(User $user, FarmAnnouncement $announcement): bool
    {
        if (! $user->can(Permission::ManageOwnFarmAnnouncements->value)) {
            return false;
        }

        if ($this->managesAnyFarm($user)) {
            return true;
        }

        return $this->scopedToFarm($user, $announcement->farm_id);
    }

    private function managesAnyFarm(User $user): bool
    {
        return $user->can(Permission::ManageFarms->value)
            && $user->can(Permission::ManageOwnFarmAnnouncements->value);
    }

    /**
     * Farmer-sellers read their farm's announcements. ManageOwnListings is
     * the farm-scoped seller permission; it is not a role-name check.
     */
    private function readsOwnFarmAnnouncements(User $user): bool
    {
        return $user->farm_id !== null
            && $user->can(Permission::ManageOwnListings->value);
    }

    private function scopedToFarm(User $user, int $farmId): bool
    {
        return $user->farm_id !== null && (int) $user->farm_id === $farmId;
    }
}
