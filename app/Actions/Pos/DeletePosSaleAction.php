<?php

namespace App\Actions\Pos;

use App\Models\Listing;
use App\Models\Sale;
use App\Support\ListingStock;
use Illuminate\Support\Facades\DB;

class DeletePosSaleAction
{
    public function __construct(private ListingStock $stock) {}

    public function handle(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale = Sale::query()
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            $listingIds = $sale->items
                ->pluck('listing_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $listings = Listing::query()
                ->whereIn('id', $listingIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Listing $listing): int => (int) $listing->id);

            foreach ($sale->items as $item) {
                $listing = $listings->get((int) $item->listing_id);

                if ($listing) {
                    $this->stock->restore(
                        $listing,
                        number_format((float) $item->quantity, 2, '.', ''),
                    );
                }
            }

            $sale->delete();
        });
    }
}
