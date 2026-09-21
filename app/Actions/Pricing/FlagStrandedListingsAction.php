<?php

namespace App\Actions\Pricing;

use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Support\InAppNotifier;
use App\Support\Pricing\PriceGuard;

/**
 * Tells sellers when a floor raise has stranded their listing. Decisions 9 and 14.
 *
 * Runs for both layers: the Super Admin raising a system floor (CropTypeObserver)
 * and a farm raising its own (SetFarmPriceOverrideAction). Both callers pass the
 * farm's effective floor before and after, so this class never has to know which
 * layer moved.
 *
 * Two ways to be stranded, one notification type:
 *
 *   Price stranded.  The listing price was legal under the old floor and is
 *                    below the new one. Checkout now refuses the listing.
 *
 *   Tawad stranded.  The price is still legal, but the active tawad rule's worst
 *                    case now takes the unit price below the new floor. Checkout
 *                    does not refuse the order; it silently drops the discount.
 *                    That silent drop is why this case is flagged at all.
 *
 * Newly stranded only. A listing already stranded under the old floor was
 * notified then, or predates this feature, and a second raise must not
 * re-notify it.
 *
 * Never moves a price, never ends a rule, never blocks the raise.
 */
class FlagStrandedListingsAction
{
    public function __construct(
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @return int the number of notifications sent
     */
    public function execute(Farm $farm, CropType $cropType, float $previousFloor, float $newFloor): int
    {
        if (PriceGuard::centavos($newFloor) <= PriceGuard::centavos($previousFloor)) {
            return 0;
        }

        // Taken-down listings are excluded: the seller cannot act on them, and
        // "update the price to keep selling" would be untrue. Inactive listings
        // are included, because a paused listing is still stranded when its
        // seller turns it back on. Soft-deleted listings are excluded by default.
        $listings = Listing::query()
            ->forFarm($farm->getKey())
            ->where('listings.crop_type_id', $cropType->getKey())
            ->whereNull('listings.taken_down_at')
            ->with(['farmerSeller', 'activeTawadRule'])
            ->get();

        $sent = 0;

        foreach ($listings as $listing) {
            $seller = $listing->farmerSeller;

            if ($seller === null) {
                continue;
            }

            $price = PriceGuard::centavos($listing->price_per_unit);

            if ($price >= PriceGuard::centavos($previousFloor) && $price < PriceGuard::centavos($newFloor)) {
                $this->notifier->floorPriceRaised($seller, $listing, $newFloor);
                $sent++;

                continue;
            }

            $rule = $listing->activeTawadRule;

            if ($price >= PriceGuard::centavos($newFloor)
                && $rule !== null
                && $rule->keepsUnitPriceAbove($listing, $previousFloor)
                && ! $rule->keepsUnitPriceAbove($listing, $newFloor)) {
                $this->notifier->tawadStrandedByFloor($seller, $listing, $newFloor);
                $sent++;
            }
        }

        return $sent;
    }
}
