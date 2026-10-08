<?php

namespace App\Actions\Pricing;

use App\Enums\ListingStatus;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Support\InAppNotifier;
use App\Support\Pricing\PriceGuard;
use App\Support\Pricing\UnitConverter;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tells sellers when a guard change has stranded their listing. Decisions 9,
 * 14, 15, and 16.
 *
 * Runs for both layers: the Super Admin changing a system value
 * (CropTypeObserver) and a farm changing its own (SetFarmPriceOverrideAction).
 * Both callers pass the farm's effective values before and after, so this class
 * never has to know which layer moved.
 *
 * Three ways to be stranded:
 *
 *   Price stranded.        The floor rose above the listing price. Checkout
 *                          refuses the listing.
 *
 *   Tawad below floor.     The floor rose, the price still clears it, but the
 *                          active rule's worst case no longer does. Checkout
 *                          silently drops the discount.
 *
 *   Tawad above ceiling.   The ceiling fell below the active rule's amount.
 *                          Checkout silently drops the discount.
 *
 * Newly stranded only: a listing already stranded before the change was told
 * then, or predates this feature. And one message per listing per change: when
 * a single write raises the floor and lowers the ceiling together, a listing
 * already told about the floor is not told again about the ceiling. The floor
 * message is the more serious of the two, so it is the one that wins.
 *
 * Never moves a price, never ends a rule, never blocks the change.
 */
class FlagStrandedListingsAction
{
    public function __construct(
        private readonly InAppNotifier $notifier,
        private readonly UnitConverter $units,
    ) {}

    /**
     * @return list<int> ids of the listings notified
     */
    public function floorRaised(Farm $farm, CropType $cropType, float $previousFloor, float $newFloor): array
    {
        if (PriceGuard::centavos($newFloor) <= PriceGuard::centavos($previousFloor)) {
            return [];
        }

        $notified = [];

        foreach ($this->candidates($farm, $cropType) as $listing) {
            $seller = $listing->farmerSeller;

            if ($seller === null) {
                continue;
            }

            $converted = $this->units->guardPrice(
                $listing->unit,
                $cropType->unit_of_measure,
                $listing->price_per_unit,
            );

            if ($converted === null) {
                continue;
            }

            $price = PriceGuard::centavos($converted);

            if ($price >= PriceGuard::centavos($previousFloor) && $price < PriceGuard::centavos($newFloor)) {
                $this->notifier->floorPriceRaised($seller, $listing, $newFloor);
                $notified[] = $listing->getKey();

                continue;
            }

            $rule = $listing->effectiveTawadRule();

            if ($price >= PriceGuard::centavos($newFloor)
                && $rule !== null
                && $rule->keepsUnitPriceAbove($listing, $previousFloor)
                && ! $rule->keepsUnitPriceAbove($listing, $newFloor)) {
                $this->notifier->tawadStrandedByFloor($seller, $listing, $newFloor);
                $notified[] = $listing->getKey();
            }
        }

        return $notified;
    }

    /**
     * @param  list<int>  $alreadyNotified  listings told about the floor in this same change
     * @return list<int> ids of the listings notified
     */
    public function ceilingLowered(
        Farm $farm,
        CropType $cropType,
        float $previousCeiling,
        float $newCeiling,
        array $alreadyNotified = [],
    ): array {
        if (PriceGuard::centavos($newCeiling) >= PriceGuard::centavos($previousCeiling)) {
            return [];
        }

        $notified = [];

        foreach ($this->candidates($farm, $cropType) as $listing) {
            if (in_array($listing->getKey(), $alreadyNotified, true)) {
                continue;
            }

            $seller = $listing->farmerSeller;
            $rule = $listing->effectiveTawadRule();

            if ($seller === null || $rule === null) {
                continue;
            }

            $converted = $this->units->guardPrice(
                $listing->unit,
                $cropType->unit_of_measure,
                $rule->discount_amount,
            );

            if ($converted === null) {
                continue;
            }

            $amount = PriceGuard::centavos($converted);

            if ($amount <= PriceGuard::centavos($previousCeiling) && $amount > PriceGuard::centavos($newCeiling)) {
                $this->notifier->tawadCeilingLowered($seller, $listing, $newCeiling);
                $notified[] = $listing->getKey();
            }
        }

        return $notified;
    }

    /**
     * Published listings whose unit cannot convert into a crop that is now
     * guarded. Same notice path as a price stranded by a raised floor.
     *
     * @return list<int>
     */
    public function incompatibleUnits(Farm $farm, CropType $cropType): array
    {
        if (! $this->units->isGuarded($cropType, $farm->getKey())) {
            return [];
        }

        $notified = [];

        foreach ($this->candidates($farm, $cropType) as $listing) {
            if ($listing->status !== ListingStatus::Published) {
                continue;
            }

            $seller = $listing->farmerSeller;
            $unit = $listing->unit;

            if ($seller === null || ($unit !== null && $cropType->unit_of_measure->convertsTo($unit))) {
                continue;
            }

            $this->notifier->listingUnitNotAllowed($seller, $listing);
            $notified[] = $listing->getKey();
        }

        return $notified;
    }

    /**
     * Taken-down listings are excluded: the seller cannot act on them. Inactive
     * listings are included, because a paused listing is still stranded when its
     * seller turns it back on. Soft-deleted listings are excluded by default.
     *
     * @return Collection<int, Listing>
     */
    private function candidates(Farm $farm, CropType $cropType): Collection
    {
        return Listing::query()
            ->forFarm($farm->getKey())
            ->where('listings.crop_type_id', $cropType->getKey())
            ->whereNull('listings.taken_down_at')
            ->with(['farmerSeller', 'farm', 'activeTawadRule', 'cropType'])
            ->get();
    }
}
