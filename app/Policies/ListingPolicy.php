<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOwnListings->value)
            || $user->can(Permission::TakedownListings->value)
            || $user->can(Permission::BrowseMarketplace->value);
    }

    public function view(User $user, Listing $listing): bool
    {
        if ($user->can(Permission::TakedownListings->value)) {
            return true;
        }

        if ($listing->isOwnedBy($user)) {
            return true;
        }

        // A buyer sees published listings only. A taken-down listing stays
        // visible to its owner so the seller can see it was removed.
        return $user->can(Permission::BrowseMarketplace->value)
            && $listing->status->isVisibleToBuyers();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnListings->value);
    }

    /**
     * A Super Admin does not edit a seller's listing. Moderation is takedown,
     * not rewriting someone else's price or copy.
     */
    public function update(User $user, Listing $listing): bool
    {
        return $user->can(Permission::ManageOwnListings->value)
            && $listing->isOwnedBy($user);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function takedown(User $user, Listing $listing): bool
    {
        return $user->can(Permission::TakedownListings->value);
    }
}
