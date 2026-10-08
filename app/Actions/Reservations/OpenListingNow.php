<?php

namespace App\Actions\Reservations;

use App\Actions\Listings\EnsureHarvestRecorded;
use App\Models\Listing;

class OpenListingNow
{
    public function __construct(
        private readonly OpenDueReservations $openDue,
        private readonly EnsureHarvestRecorded $ensureHarvest,
    ) {}

    public function handle(Listing $listing): Listing
    {
        $listing->available_from = now();
        $listing->save();

        $this->ensureHarvest->forListing($listing);

        $this->openDue->forListing($listing->id);

        return $listing->refresh();
    }
}
