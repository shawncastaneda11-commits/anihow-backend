<?php

namespace App\Actions\Listings;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\GuardListingReservationCancellation;
use App\Enums\ReservationCancellationReason;
use App\Models\Farm;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;

class UpdateListingAction
{
    public function __construct(
        private SyncListingImage $images,
        private GuardListingReservationCancellation $reservationGuard,
        private CancelReservation $cancelReservation,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Listing $listing,
        array $attributes,
        ?UploadedFile $image = null,
        bool $confirmCancelReservations = false,
    ): Listing {
        if ($image !== null) {
            $attributes['image_path'] = $this->images->replace($listing->image_path, $image);
        }

        // farm_id is denormalized from the seller and is never set from input.
        unset($attributes['farm_id'], $attributes['farmer_seller_id'], $attributes['quantity_held'], $attributes['status']);

        if (array_key_exists('crop_type_id', $attributes) && (int) $attributes['crop_type_id'] !== (int) $listing->crop_type_id) {
            $listing->loadMissing('farm');
            CreateListingAction::assertValueAddedAllowed(
                $listing->farm instanceof Farm ? $listing->farm : null,
                (int) $attributes['crop_type_id'],
            );
        }

        $turningOff = array_key_exists('is_active', $attributes) && ! $attributes['is_active'];

        if ($turningOff) {
            $this->reservationGuard->ensure($listing, $confirmCancelReservations);
        }

        $listing->update($attributes);

        if ($turningOff) {
            $this->cancelReservation->forListing($listing, ReservationCancellationReason::ListingRemoved);
        }

        return $listing->refresh();
    }
}
