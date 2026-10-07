<?php

namespace App\Actions\Reservations;

use App\Enums\FulfillmentPreference;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OrderLinePricer;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveListing
{
    public const int ActiveCap = 3;

    public function __construct(
        private readonly OrderLinePricer $pricer,
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @return array{reservation: Reservation, created: bool}
     */
    public function handle(
        User $buyer,
        int $listingId,
        float $quantity,
        FulfillmentPreference $preference,
        ?string $note,
    ): array {
        return DB::transaction(function () use ($buyer, $listingId, $quantity, $preference, $note): array {
            // Lock the buyer before counting so two reserves cannot both pass the cap of 3.
            User::query()->whereKey($buyer->id)->lockForUpdate()->first();

            $listing = Listing::query()
                ->whereKey($listingId)
                ->with(['cropType', 'activeTawadRule', 'farm.cropTypeOverrides', 'farmerSeller'])
                ->lockForUpdate()
                ->first();

            if ($listing === null || ! Listing::query()->listedForBuyers()->whereKey($listing->id)->exists()) {
                abort(404);
            }

            if (! $listing->isUpcoming()) {
                throw ValidationException::withMessages([
                    'listing_id' => "{$listing->title} is not available to reserve.",
                ]);
            }

            $existing = Reservation::query()
                ->where('buyer_id', $buyer->id)
                ->where('listing_id', $listing->id)
                ->where('status', ReservationStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                $activeCount = Reservation::query()
                    ->where('buyer_id', $buyer->id)
                    ->where('status', ReservationStatus::Active)
                    ->count();

                if ($activeCount >= self::ActiveCap) {
                    throw ValidationException::withMessages([
                        'listing_id' => 'You can have at most 3 active reservations.',
                    ]);
                }
            }

            $reservedByOthers = (float) Reservation::query()
                ->where('listing_id', $listing->id)
                ->where('status', ReservationStatus::Active)
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))
                ->sum('quantity');

            $remaining = (float) $listing->quantity_available - $reservedByOthers;

            if ($quantity > $remaining) {
                $available = number_format(max(0, $remaining), 2, '.', '');

                throw ValidationException::withMessages([
                    'quantity' => "Only {$available} available.",
                ]);
            }

            $left = min($listing->sellableQuantity(), $remaining);

            if (Listing::orderHundredths($left) < Listing::orderHundredths((float) $listing->min_order_quantity)) {
                throw ValidationException::withMessages([
                    'quantity' => $listing->belowMinimumStockMessage($left),
                ]);
            }

            if (! $listing->allowsOrderQuantity($quantity)) {
                throw ValidationException::withMessages([
                    'quantity' => $listing->orderQuantityMessage(),
                ]);
            }

            $priced = $this->pricer->price(
                $listing,
                $quantity,
                'listing_id',
                "{$listing->title} is priced below the current floor price and cannot be reserved.",
            );

            $attributes = [
                'quantity' => $priced['quantity'],
                'unit' => $priced['unit'],
                'unit_price' => $priced['unit_price'],
                'line_subtotal' => $priced['line_subtotal'],
                'tawad_amount' => $priced['tawad_amount'],
                'line_total' => $priced['line_total'],
                'crop_type_id' => $priced['crop_type_id'],
                'listing_name' => $priced['listing_name'],
                'tawad_rule_id' => $priced['tawad_rule_id'],
                'tawad_type' => $priced['tawad_type'],
                'fulfillment_preference' => $preference,
                'fulfillment_note' => $note,
                'active_slot' => "{$buyer->id}:{$listing->id}",
            ];

            if ($existing !== null) {
                $existing->update($attributes);

                return [
                    'reservation' => $existing->refresh(),
                    'created' => false,
                ];
            }

            $reservation = Reservation::query()->create([
                'buyer_id' => $buyer->id,
                'listing_id' => $listing->id,
                'farmer_seller_id' => $listing->farmer_seller_id,
                'farm_id' => $listing->farm_id,
                'status' => ReservationStatus::Active,
                ...$attributes,
            ]);

            if ($listing->farmerSeller !== null) {
                $this->notifier->reservationMade($listing->farmerSeller, $reservation);
            }

            return [
                'reservation' => $reservation,
                'created' => true,
            ];
        });
    }
}
