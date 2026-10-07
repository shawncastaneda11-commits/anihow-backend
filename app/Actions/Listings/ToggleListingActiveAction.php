<?php

namespace App\Actions\Listings;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\GuardListingReservationCancellation;
use App\Enums\ListingStatus;
use App\Enums\ReservationCancellationReason;
use App\Models\Listing;
use Illuminate\Validation\ValidationException;

class ToggleListingActiveAction
{
    public function __construct(
        private readonly CancelReservation $cancelReservation,
        private readonly GuardListingReservationCancellation $reservationGuard,
    ) {}

    /**
     * The seller's own availability switch. Distinct from status, which is
     * moderation and belongs to the Super Admin. A taken-down listing stays
     * off until an administrator restores it.
     */
    public function handle(Listing $listing, ?bool $isActive = null, bool $confirmCancelReservations = false): Listing
    {
        if ($listing->status === ListingStatus::TakenDown) {
            throw ValidationException::withMessages([
                'is_active' => 'This listing was taken down by an administrator.',
            ]);
        }

        $next = $isActive ?? ! $listing->is_active;

        if ($next === false) {
            $this->reservationGuard->ensure($listing, $confirmCancelReservations);
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
