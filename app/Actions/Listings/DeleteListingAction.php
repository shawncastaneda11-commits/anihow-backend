<?php

namespace App\Actions\Listings;

use App\Models\Listing;
use Illuminate\Validation\ValidationException;

class DeleteListingAction
{
    /**
     * Soft delete. Order items snapshot everything they display, so history
     * survives, but stock held by a placed order must be settled first or the
     * buyer loses an order they are waiting on.
     */
    public function handle(Listing $listing): void
    {
        if ((float) $listing->quantity_held > 0) {
            throw ValidationException::withMessages([
                'listing' => 'This listing has pending orders. Confirm or cancel them before deleting it.',
            ]);
        }

        $listing->delete();
    }
}
