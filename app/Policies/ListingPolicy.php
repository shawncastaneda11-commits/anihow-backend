<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isFarmerSeller();
    }

    public function view(User $user, Listing $listing): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isFarmerSeller() && $listing->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isFarmerSeller();
    }

    public function update(User $user, Listing $listing): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isFarmerSeller() && $listing->isOwnedBy($user);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
