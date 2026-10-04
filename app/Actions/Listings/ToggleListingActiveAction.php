<?php

namespace App\Actions\Listings;

use App\Actions\Reservations\CancelReservation;
use App\Enums\ListingStatus;
use App\Enums\ReservationCancellationReason;
use App\Models\Listing;
use Illuminate\Validation\ValidationException;

class ToggleListingActiveAction
{
    public function __construct(private readonly CancelReservation $cancelReservation) {}

    /**
     * The seller's own availability switch. Distinct from status, which is
     * moderation and belongs to the Super Admin. A taken-down listing stays
     * off until an administrator restores it.
     */
    public function handle(Listing $listing, ?bool $isActive = null): Listing
    {
        if ($listing->status === ListingStatus::TakenDown) {
            throw ValidationException::withMessages([
                'is_active' => 'This listing was taken down by an administrator.',
            ]);
        }

        $listing->update([
            'is_active' => $isActive ?? ! $listing->is_active,
        ]);

        $listing->refresh();

        if (! $listing->is_active) {
            $this->cancelReservation->forListing($listing, ReservationCancellationReason::ListingRemoved);
        }

        return $listing;
    }
}
