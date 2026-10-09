<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Enums\ProductCategory;
use App\Models\HarvestRecord;
use App\Models\Listing;

class WriteHarvestRecord
{
    /**
     * Opening records are a starting balance, not a harvest. Prompt 9's
     * harvest charts exclude kind = opening.
     *
     * @param  array{
     *     harvested_on: string,
     *     quantity_harvested: string,
     *     quantity_rejected: string,
     *     quantity_good: string,
     *     rejection_reason: ?string,
     *     rejection_note: ?string,
     *     production_cost: ?string,
     *     cost_breakdown: ?array<string, string>
     * }  $harvest
     */
    public function write(
        Listing $listing,
        HarvestRecordKind $kind,
        array $harvest,
        ?int $recordedBy,
    ): HarvestRecord {
        $listing->loadMissing('cropType');

        if (in_array($kind, [HarvestRecordKind::Initial, HarvestRecordKind::Added, HarvestRecordKind::Actual], true)) {
            $current = $listing->harvested_on?->toDateString();
            if ($current === null || $harvest['harvested_on'] > $current) {
                $listing->harvested_on = $harvest['harvested_on'];
            }
        }

        if ($kind === HarvestRecordKind::Added) {
            $listing->quantity_available = bcadd((string) $listing->quantity_available, $harvest['quantity_good'], 2);
        }

        if ($kind === HarvestRecordKind::Actual) {
            $listing->quantity_available = $harvest['quantity_good'];
            $listing->needs_actual_harvest = false;
        }

        if ($kind === HarvestRecordKind::Estimated) {
            $listing->needs_actual_harvest = false;
        }

        $listing->save();

        $category = $listing->cropType?->category;

        return $listing->harvestRecords()->create([
            'farmer_seller_id' => $listing->farmer_seller_id,
            'farm_id' => $listing->farm_id,
            'crop_type_id' => $listing->crop_type_id,
            'unit' => $listing->unit?->value ?? (string) $listing->getRawOriginal('unit'),
            'is_value_added' => $category === ProductCategory::ValueAdded,
            'harvested_on' => $harvest['harvested_on'],
            'quantity_harvested' => $harvest['quantity_harvested'],
            'quantity_rejected' => $harvest['quantity_rejected'],
            'quantity_good' => $harvest['quantity_good'],
            'rejection_reason' => $harvest['rejection_reason'],
            'rejection_note' => $harvest['rejection_note'],
            'price_per_unit' => $listing->price_per_unit,
            'production_cost' => $harvest['production_cost'],
            'cost_breakdown' => $harvest['cost_breakdown'],
            'kind' => $kind,
            'recorded_by' => $recordedBy,
        ]);
    }
}
