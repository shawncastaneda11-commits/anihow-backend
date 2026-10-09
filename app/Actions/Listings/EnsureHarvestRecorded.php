<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Models\Listing;
use App\Support\HarvestInput;
use Illuminate\Support\Facades\DB;

class EnsureHarvestRecorded
{
    public function __construct(private WriteHarvestRecord $records) {}

    public function forListing(Listing $listing): Listing
    {
        $apply = function () use ($listing): Listing {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->needs_actual_harvest || ! $this->windowHasOpened($locked)) {
                return $listing->fresh() ?? $listing;
            }

            $quantity = HarvestInput::scale($locked->quantity_available);

            $this->records->write($locked, HarvestRecordKind::Estimated, [
                'harvested_on' => $locked->harvested_on?->toDateString() ?? now()->toDateString(),
                'quantity_harvested' => $quantity,
                'quantity_rejected' => '0.00',
                'quantity_good' => $quantity,
                'rejection_reason' => null,
                'rejection_note' => null,
                'production_cost' => null,
                'cost_breakdown' => null,
            ], null);

            return $locked->refresh();
        };

        if (DB::transactionLevel() > 0) {
            return $apply();
        }

        return DB::transaction($apply);
    }

    private function windowHasOpened(Listing $listing): bool
    {
        return $listing->available_from === null || ! $listing->available_from->isFuture();
    }
}
