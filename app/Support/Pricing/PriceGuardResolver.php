<?php

namespace App\Support\Pricing;

use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;

/**
 * Resolves the effective floor and the effective discount ceiling for a farm
 * and a crop type.
 *
 *   effective floor   = max(system floor,  farm floor override)
 *   effective ceiling = min(system maximum, farm ceiling override)
 *
 * Both max() and min() are belt and braces: tighten-only validation already
 * guarantees the override sits on the correct side. Keeping them here means a
 * row written directly in the database by a fixture or a console command cannot
 * loosen a guard.
 *
 * Eager loading. This runs per line at checkout and per row in the Filament
 * listing table. When farm.cropTypeOverrides is already loaded the resolver
 * reads it from memory and issues no query. Load it as a whole relation rather
 * than one constrained row per call: a farm carries a handful of overrides, and
 * a constrained eager load resolves only one crop type per farm.
 *
 *   Listing::with(['cropType', 'farm.cropTypeOverrides'])
 */
class PriceGuardResolver
{
    public function for(Farm $farm, CropType $cropType): PriceGuard
    {
        $override = $farm->overrideFor($cropType->getKey());

        $systemFloor = (float) $cropType->floor_price;
        $systemMaximum = (float) $cropType->max_discount;

        return new PriceGuard(
            floor: max($systemFloor, (float) ($override?->floor_price ?? $systemFloor)),
            ceiling: min($systemMaximum, (float) ($override?->max_discount ?? $systemMaximum)),
        );
    }

    /**
     * Convenience for the enforcement points in Pass 2C. Expects farm and
     * cropType to be loaded; see the eager-load note above.
     */
    public function forListing(Listing $listing): PriceGuard
    {
        return $this->for($listing->farm, $listing->cropType);
    }

    /** The system values with no farm layer, for Super Admin system-wide views. */
    public function system(CropType $cropType): PriceGuard
    {
        return new PriceGuard(
            floor: (float) $cropType->floor_price,
            ceiling: (float) $cropType->max_discount,
        );
    }
}
