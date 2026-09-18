<?php

namespace App\Actions\Listings;

use App\Models\Listing;

class ToggleListingActiveAction
{
    public function handle(Listing $listing, ?bool $isActive = null): Listing
    {
        $listing->update([
            'is_active' => $isActive ?? ! $listing->is_active,
        ]);

        return $listing->refresh()->load(['category', 'farmerSeller']);
    }
}
