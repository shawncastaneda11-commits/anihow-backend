<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Enums\ProductCategory;
use App\Models\Listing;
use App\Support\HarvestInput;

/**
 * Existing listings gain a starting balance. Opening records are NOT harvests:
 * Prompt 9's harvest charts will exclude kind = opening.
 */
class BackfillOpeningHarvestRecords
{
    public function handle(): void
    {
        Listing::query()
            ->with('cropType')
            ->orderBy('id')
            ->chunkById(100, function ($listings): void {
                foreach ($listings as $listing) {
                    $this->backfill($listing);
                }
            });
    }

    private function backfill(Listing $listing): void
    {
        $listing->stock_tracked_since = now();

        $upcoming = $listing->available_from !== null
            && $listing->available_from->isFuture()
            && ! $listing->isExpired();

        if ($upcoming) {
            $listing->needs_actual_harvest = true;
            $listing->save();

            return;
        }

        $listing->needs_actual_harvest = false;
        $listing->save();

        $quantity = HarvestInput::scale($listing->quantity_available);

        if (bccomp($quantity, '0', 2) !== 1 || $listing->farm_id === null || $listing->crop_type_id === null) {
            return;
        }

        $listing->harvestRecords()->create([
            'farmer_seller_id' => $listing->farmer_seller_id,
            'farm_id' => $listing->farm_id,
            'crop_type_id' => $listing->crop_type_id,
            'unit' => $listing->unit?->value ?? (string) $listing->getRawOriginal('unit'),
            'is_value_added' => $listing->cropType?->category === ProductCategory::ValueAdded,
            'harvested_on' => $listing->harvested_on?->toDateString() ?? now()->toDateString(),
            'quantity_harvested' => $quantity,
            'quantity_rejected' => '0.00',
            'quantity_good' => $quantity,
            'price_per_unit' => $listing->price_per_unit ?? '0.0000',
            'production_cost' => null,
            'cost_breakdown' => null,
            'kind' => HarvestRecordKind::Opening,
            'recorded_by' => null,
        ]);
    }
}
