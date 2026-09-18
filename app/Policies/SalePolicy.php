<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isFarmerSeller() || $user->isSuperAdmin();
    }

    public function view(User $user, Sale $sale): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isFarmerSeller() && $sale->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->isFarmerSeller();
    }
}
