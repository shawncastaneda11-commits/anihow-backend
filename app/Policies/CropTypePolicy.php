<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CropType;
use App\Models\User;

/**
 * The taxonomy is system-wide and Super Admin owned. No Content Editor and no
 * Farmer-Seller may write it. If each farm could set its own floor, the floor
 * would stop being a guardrail.
 */
class CropTypePolicy
{
    public function viewAny(User $user): bool
    {
        // Everyone reads the taxonomy: sellers pick from it, buyers browse by
        // it, content editors tag articles to it.
        return true;
    }

    public function view(User $user, CropType $cropType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCropTypes->value);
    }

    public function update(User $user, CropType $cropType): bool
    {
        return $user->can(Permission::ManageCropTypes->value);
    }

    public function delete(User $user, CropType $cropType): bool
    {
        return $user->can(Permission::ManageCropTypes->value);
    }

    /**
     * floor_price and max_discount are locked to the Super Admin separately
     * from the rest of the taxonomy entry. Call this from the form as well,
     * or a future editor role inherits pricing power by accident.
     */
    public function setPricing(User $user, ?CropType $cropType = null): bool
    {
        return $user->can(Permission::SetCropPricing->value);
    }
}
