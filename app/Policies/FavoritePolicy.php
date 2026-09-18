<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

class FavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBuyer();
    }

    public function create(User $user): bool
    {
        return $user->isBuyer();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $user->isBuyer() && $favorite->buyer_id === $user->id;
    }
}
