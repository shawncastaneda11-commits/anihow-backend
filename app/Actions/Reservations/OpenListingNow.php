<?php

namespace App\Actions\Reservations;

use App\Models\Listing;

class OpenListingNow
{
    public function __construct(private readonly OpenDueReservations $openDue) {}

    public function handle(Listing $listing): Listing
    {
        $listing->available_from = now();
        $listing->save();

        $this->openDue->forListing($listing->id);

        return $listing->refresh();
    }
}
