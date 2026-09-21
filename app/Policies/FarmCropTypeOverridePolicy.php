<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Models\User;

/**
 * Two roles reach farm price overrides, with different span.
 *
 * Super Admin writes any farm's overrides. Section 4 puts economic guardrails
 * in their column, and a suspended Content Editor would otherwise leave a
 * farm's numbers unfixable.
 *
 * Content Editor writes their own farm's overrides only.
 *
 * The span lives here and nowhere else. There is no second permission, no
 * second role, and no scope check duplicated into a FormRequest. Decision 11.
 *
 * Tighten-only is a validation concern, not an authorization one:
 * SetFarmPriceOverrideAction enforces it for both roles alike. Super Admin
 * loosens a guard by raising the system value on the crop type, which is a
 * different resource with its own policy.
 */
class FarmCropTypeOverridePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $this->isContentEditor($user);
    }

    public function view(User $user, FarmCropTypeOverride $override): bool
    {
        return $this->reaches($user, $override->farm_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FarmCropTypeOverride $override): bool
    {
        return $this->reaches($user, $override->farm_id);
    }

    public function delete(User $user, FarmCropTypeOverride $override): bool
    {
        return $this->reaches($user, $override->farm_id);
    }

    /**
     * For the create path and the Filament relation manager, where the farm is
     * known from the parent record but no override model exists yet.
     */
    public function manageForFarm(User $user, Farm $farm): bool
    {
        return $this->reaches($user, $farm->getKey());
    }

    private function reaches(User $user, ?int $farmId): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isContentEditor($user)
            && $farmId !== null
            && $user->farm_id === $farmId;
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin->value);
    }

    private function isContentEditor(User $user): bool
    {
        return $user->hasRole(Role::ContentEditor->value);
    }
}
