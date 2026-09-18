<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Validation\ValidationException;

class ListingStock
{
    public function __construct(private InAppNotifier $notifier) {}

    public function decrement(Listing $listing, string $quantity): void
    {
        $before = (string) $listing->quantity_available;

        if (bccomp($before, $quantity, 2) < 0) {
            $unit = $listing->unit?->value ?? '';

            throw ValidationException::withMessages([
                'items' => "Only {$listing->quantity_available} {$unit} of {$listing->name} available.",
            ]);
        }

        $after = bcsub($before, $quantity, 2);

        $listing->update([
            'quantity_available' => $after,
        ]);

        $threshold = number_format((float) config('anihow.low_stock_threshold', 5), 2, '.', '');

        if (bccomp($before, $threshold, 2) >= 0 && bccomp($after, $threshold, 2) < 0) {
            $farmer = $listing->farmerSeller()->first();

            if ($farmer) {
                $this->notifier->listingLowStock($farmer, $listing->refresh());
            }
        }
    }

    public function restore(Listing $listing, string $quantity): void
    {
        $listing->update([
            'quantity_available' => bcadd((string) $listing->quantity_available, $quantity, 2),
        ]);
    }
}
