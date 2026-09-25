<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FaqEntry;
use App\Models\User;

/**
 * Super Admins write system-wide rows and moderate farm rows: they may
 * deactivate or delete a farm override, but they do not rewrite farm text.
 * Content Editors write farmer-seller rows for their own farm and may read
 * system rows so they know what they are overriding.
 */
class FaqEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesSystem($user) || $this->managesOwnFarm($user);
    }

    public function view(User $user, FaqEntry $faqEntry): bool
    {
        if ($this->managesSystem($user)) {
            return true;
        }

        if (! $this->managesOwnFarm($user)) {
            return false;
        }

        return $faqEntry->isSystemWide()
            || ($user->farm_id !== null && $faqEntry->belongsToFarm($user->farm_id));
    }

    public function create(User $user): bool
    {
        return $this->managesSystem($user)
            || ($this->managesOwnFarm($user) && $user->farm_id !== null);
    }

    public function update(User $user, FaqEntry $faqEntry): bool
    {
        return $this->writes($user, $faqEntry);
    }

    public function delete(User $user, FaqEntry $faqEntry): bool
    {
        if ($this->managesSystem($user)) {
            return true;
        }

        return $this->writes($user, $faqEntry);
    }

    /**
     * Moderation: hide or restore a farm override without editing its copy.
     */
    public function deactivate(User $user, FaqEntry $faqEntry): bool
    {
        return $this->managesSystem($user) && ! $faqEntry->isSystemWide();
    }

    private function writes(User $user, FaqEntry $faqEntry): bool
    {
        if ($this->managesSystem($user)) {
            return $faqEntry->isSystemWide();
        }

        return $this->managesOwnFarm($user)
            && $user->farm_id !== null
            && $faqEntry->belongsToFarm($user->farm_id)
            && $faqEntry->isFarmerSellerFacingOnly();
    }

    private function managesSystem(User $user): bool
    {
        return $user->can(Permission::ManageSystemFaq->value);
    }

    private function managesOwnFarm(User $user): bool
    {
        return $user->can(Permission::ManageOwnFarmFaq->value);
    }
}
