<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CartItem;
use App\Models\User;

class CartItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PlaceOrders->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PlaceOrders->value);
    }

    public function update(User $user, CartItem $item): bool
    {
        return $user->can(Permission::PlaceOrders->value) && $item->isOwnedBy($user);
    }

    public function delete(User $user, CartItem $item): bool
    {
        return $this->update($user, $item);
    }
}
