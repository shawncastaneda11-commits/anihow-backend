<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBuyer() || $user->isFarmerSeller() || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isBuyer();
    }

    public function view(User $user, Review $review): bool
    {
        return $user->isBuyer() || $user->isFarmerSeller() || $user->isSuperAdmin();
    }
}
