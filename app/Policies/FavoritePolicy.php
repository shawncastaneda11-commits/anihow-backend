<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Favorite;
use App\Models\User;

class FavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BrowseMarketplace->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BrowseMarketplace->value);
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->isOwnedBy($user);
    }
}
