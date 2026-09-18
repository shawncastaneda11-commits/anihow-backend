<?php

namespace App\Actions\Listings;

use App\Models\Listing;
use App\Models\ReservationItem;
use App\Models\SaleItem;
use Illuminate\Validation\ValidationException;

class DeleteListingAction
{
    public function handle(Listing $listing): void
    {
        $inUse = ReservationItem::query()->where('listing_id', $listing->id)->exists()
            || SaleItem::query()->where('listing_id', $listing->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'listing' => 'This listing cannot be deleted because it was used on a reservation or sale.',
            ]);
        }

        $listing->delete();
    }
}
