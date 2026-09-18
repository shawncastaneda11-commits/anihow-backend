<?php

namespace App\Actions\Listings;

use App\Models\Listing;

class DeleteListingAction
{
    public function handle(Listing $listing): void
    {
        $listing->delete();
    }
}
