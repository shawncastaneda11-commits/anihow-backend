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
 * The same max() exists once more in SQL, in the "Priced below floor" filter on
 * ListingsTable. If this formula changes, change that one too.
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
     * For the enforcement points that already hold a listing. Expects farm and
     * cropType to be loaded; see the eager-load note above.
     */
    public function forListing(Listing $listing): PriceGuard
    {
        return $this->for($listing->farm, $listing->cropType);
    }

    /**
     * For a request that knows a farm id but holds no loaded Farm: a seller
     * creating a listing, or a request working from a listing's farm_id.
     *
     * A null id, or an id that no longer resolves, means no farm layer applies
     * and the system values stand. The constrained eager load is correct here:
     * exactly one crop type is being asked about.
     */
    public function forFarmId(int|string|null $farmId, CropType $cropType): PriceGuard
    {
        if ($farmId === null) {
            return $this->system($cropType);
        }

        $farm = Farm::query()
            ->with(['cropTypeOverrides' => fn ($query) => $query->where('crop_type_id', $cropType->getKey())])
            ->find($farmId);

        return $farm !== null
            ? $this->for($farm, $cropType)
            : $this->system($cropType);
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
