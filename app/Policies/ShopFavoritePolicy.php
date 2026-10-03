<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ShopFavorite;
use App\Models\User;

class ShopFavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BrowseMarketplace->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BrowseMarketplace->value);
    }

    public function delete(User $user, ShopFavorite $shopFavorite): bool
    {
        return $shopFavorite->isOwnedBy($user);
    }
}
