<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddStockAction
{
    public function __construct(private WriteHarvestRecord $records) {}

    /**
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
    public function handle(Listing $listing, array $harvest, User $actor): Listing
    {
        return DB::transaction(function () use ($listing, $harvest, $actor): Listing {
            $locked = Listing::withTrashed()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $this->assertCanAdd($locked);
            $this->records->write($locked, HarvestRecordKind::Added, $harvest, $actor->id);

            return $locked->refresh();
        });
    }

    private function assertCanAdd(Listing $listing): void
    {
        if ($listing->trashed()) {
            throw ValidationException::withMessages([
                'listing' => 'This listing has been deleted.',
            ]);
        }

        if ($listing->status === ListingStatus::TakenDown) {
            throw ValidationException::withMessages([
                'listing' => 'This listing has been taken down.',
            ]);
        }

        if ($listing->needs_actual_harvest) {
            throw ValidationException::withMessages([
                'listing' => 'Record the actual harvest first',
            ]);
        }

        if ($listing->isExpired()) {
            throw ValidationException::withMessages([
                'listing' => 'Extend the listing first',
            ]);
        }
    }
}
