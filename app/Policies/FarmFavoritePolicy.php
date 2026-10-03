<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FarmFavorite;
use App\Models\User;

class FarmFavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BrowseMarketplace->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BrowseMarketplace->value);
    }

    public function delete(User $user, FarmFavorite $farmFavorite): bool
    {
        return $farmFavorite->isOwnedBy($user);
    }
}
