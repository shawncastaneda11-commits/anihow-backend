<?php

namespace App\Actions\Listings;

use App\Models\Listing;

class ToggleListingActiveAction
{
    /**
     * The seller's own availability switch. Distinct from status, which is
     * moderation and belongs to the Super Admin.
     */
    public function handle(Listing $listing, ?bool $isActive = null): Listing
    {
        $listing->update([
            'is_active' => $isActive ?? ! $listing->is_active,
        ]);

        return $listing->refresh();
    }
}
