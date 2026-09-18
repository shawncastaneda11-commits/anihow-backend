<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use App\Support\InAppNotifier;
use App\Support\ListingStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReservationAction
{
    public function __construct(
        private ListingStock $stock,
        private InAppNotifier $notifier,
    ) {}

    /**
     * @param  list<array{listing_id: int, quantity: numeric-string|int|float}>  $items
     */
    public function handle(User $buyer, array $items, ?string $notes = null): Reservation
    {
        return DB::transaction(function () use ($buyer, $items, $notes): Reservation {
            $requested = $this->mergeQuantities($items);
            $listings = Listing::query()
                ->marketplaceVisible()
                ->whereIn('id', $requested->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Listing $listing): int => (int) $listing->id);

            if ($listings->count() !== $requested->count()) {
                throw ValidationException::withMessages([
                    'items' => 'One or more listings are not available for reservation.',
                ]);
            }

            $farmerIds = $listings->pluck('farmer_seller_id')->unique();

            if ($farmerIds->count() !== 1) {
                throw ValidationException::withMessages([
                    'items' => 'A reservation can only include listings from one farmer-seller.',
                ]);
            }

            $lineItems = [];
            $total = '0.00';

            foreach ($requested as $listingId => $quantity) {
                $listing = $listings->get((int) $listingId);
                $qty = number_format((float) $quantity, 2, '.', '');
                $this->stock->decrement($listing, $qty);

                $unitPrice = number_format((float) $listing->price_per_unit, 2, '.', '');
                $line = bcmul($unitPrice, $qty, 2);
                $total = bcadd($total, $line, 2);

                $lineItems[] = [
                    'listing_id' => $listing->id,
                    'listing_name' => $listing->name,
                    'unit' => $listing->unit->value,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $line,
                ];
            }

            $reservation = Reservation::query()->create([
                'buyer_id' => $buyer->id,
                'farmer_seller_id' => $farmerIds->first(),
                'status' => ReservationStatus::Pending,
                'total' => $total,
                'notes' => $notes,
            ]);

            $reservation->items()->createMany($lineItems);

            $reservation = $reservation->load(['items.listing', 'buyer', 'farmerSeller']);
            $this->notifier->reservationCreated($reservation->farmerSeller, $reservation);

            return $reservation;
        });
    }

    /**
     * @param  list<array{listing_id: int, quantity: numeric-string|int|float}>  $items
     * @return Collection<int, string>
     */
    private function mergeQuantities(array $items): Collection
    {
        return collect($items)
            ->groupBy(fn (array $item): int => (int) $item['listing_id'])
            ->map(fn (Collection $group): string => number_format(
                $group->sum(fn (array $item): float => (float) $item['quantity']),
                2,
                '.',
                '',
            ));
    }
}
