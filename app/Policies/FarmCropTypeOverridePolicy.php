<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Models\User;

/**
 * Farm price overrides, tighten-only. Decision 13.
 *
 * Holding SetFarmPricing lets a user write overrides at all. Which farms they
 * reach is decided the same way FarmResource decides it: a holder of
 * ManageFarms reaches every farm, anyone else reaches only their own farm_id.
 *
 * In practice that is the Super Admin across every farm, and a Content Editor
 * inside their own. The Super Admin reaches every farm so that a suspended
 * Content Editor cannot leave a farm's numbers unfixable.
 *
 * Tighten-only is a validation concern, not an authorization one.
 * SetFarmPriceOverrideAction enforces it for every caller alike. Loosening a
 * guard means changing the system value on the crop type, which is a different
 * resource behind CropTypePolicy::setPricing.
 */
class FarmCropTypeOverridePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SetFarmPricing->value);
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

    private function reaches(User $user, int|string|null $farmId): bool
    {
        if (! $user->can(Permission::SetFarmPricing->value)) {
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
