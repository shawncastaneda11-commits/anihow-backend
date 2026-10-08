<?php

namespace App\Actions\Listings;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\GuardListingReservationCancellation;
use App\Enums\ReservationCancellationReason;
use App\Models\Farm;
use App\Models\Listing;
use App\Support\HarvestInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateListingAction
{
    public function __construct(
        private SyncListingImage $images,
        private GuardListingReservationCancellation $reservationGuard,
        private CancelReservation $cancelReservation,
        private EnsureHarvestRecorded $ensureHarvest,
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

        $updated = DB::transaction(function () use ($listing, $attributes): Listing {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $attributes = $this->guardStockEdits($locked, $attributes);
            $locked->update($attributes);
            $this->ensureHarvest->forListing($locked->refresh());

            return $locked->refresh();
        });

        if ($turningOff) {
            $this->cancelReservation->forListing($updated, ReservationCancellationReason::ListingRemoved);
        }

        return $updated->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function guardStockEdits(Listing $listing, array $attributes): array
    {
        if (array_key_exists('quantity_available', $attributes)) {
            $sent = HarvestInput::scale($attributes['quantity_available']);
            $current = HarvestInput::scale($listing->quantity_available);

            if (bccomp($sent, $current, 2) === 0) {
                unset($attributes['quantity_available']);
            } elseif ($listing->needs_actual_harvest) {
                $reserved = $listing->activeReservedQuantity();

                if (bccomp($sent, $reserved, 2) === -1) {
                    $unit = $listing->unit?->value ?? '';

                    throw ValidationException::withMessages([
                        'quantity_available' => "{$reserved} {$unit} is already reserved.",
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'quantity_available' => 'Use Add stock or Remove stock to change the quantity.',
                ]);
            }
        }

        if (! $listing->hasNonOpeningHarvestRecord()) {
            return $attributes;
        }

        $cropChanged = array_key_exists('crop_type_id', $attributes)
            && (int) $attributes['crop_type_id'] !== (int) $listing->crop_type_id;
        $unitChanged = array_key_exists('unit', $attributes)
            && (string) $attributes['unit'] !== (string) ($listing->unit?->value ?? $listing->getRawOriginal('unit'));

        if ($cropChanged || $unitChanged) {
            throw ValidationException::withMessages([
                $cropChanged ? 'crop_type_id' : 'unit' => 'Create a new listing for a different crop or unit.',
            ]);
        }

        return $attributes;
    }
}
