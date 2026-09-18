<?php

namespace App\Actions\Pos;

use App\Models\Listing;
use App\Models\Sale;
use App\Models\User;
use App\Support\ListingStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPosSaleAction
{
    public function __construct(private ListingStock $stock) {}

    /**
     * @param  list<array{listing_id: int, quantity: numeric-string|int|float}>  $items
     */
    public function handle(User $farmer, array $items, ?string $notes = null): Sale
    {
        return DB::transaction(function () use ($farmer, $items, $notes): Sale {
            $requested = $this->mergeQuantities($items);
            $listings = Listing::query()
                ->where('farmer_seller_id', $farmer->id)
                ->whereIn('id', $requested->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Listing $listing): int => (int) $listing->id);

            if ($listings->count() !== $requested->count()) {
                throw ValidationException::withMessages([
                    'items' => 'POS sales can only include your own listings.',
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

            $sale = $farmer->sales()->create([
                'total' => $total,
                'notes' => $notes,
            ]);

            $sale->items()->createMany($lineItems);

            return $sale->load(['items.listing', 'farmerSeller']);
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
