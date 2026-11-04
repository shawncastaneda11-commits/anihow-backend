<?php

namespace App\Support\Pricing;

use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Models\Listing;

/**
 * Resolves the effective floor and the effective discount ceiling for a farm
 * and a crop type.
 *
 *   effective floor   = max(system floor,  farm floor override)
 *   effective ceiling = min(system maximum, farm ceiling override), where a
 *                       blank maximum means none: the floor alone limits tawad
 *
 * Both max() and min() are belt and braces: tighten-only validation already
 * guarantees the override sits on the correct side. Keeping them here means a
 * row written directly in the database by a fixture or a console command cannot
 * loosen a guard.
 *
 * Units. The crop's own values cover its main unit and every unit that
 * converts to it. A listing in another unit family is checked against the
 * crop's extra row for that family (CropTypeUnitGuard), tightened by the
 * farm's override for that unit. A listing unit that neither covers gets the
 * main guard, which its price cannot convert into, so it is refused.
 *
 * The same max() exists once more in SQL, in the "Priced below floor" filter on
 * ListingsTable. If this formula changes, change that one too. That filter
 * converts the listing price into the crop type's unit before comparing, via
 * UnitConverter, so a per-gram price is judged against a per-kilogram floor.
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
    public function for(Farm $farm, CropType $cropType, ?ListingUnit $unit = null): PriceGuard
    {
        $row = $cropType->unitGuardFor($unit);

        return $this->combine($cropType, $row, $farm->overrideFor($cropType->getKey(), $row?->unit));
    }

    /**
     * The stricter of two ceilings, where null means no maximum. A farm may
     * set a maximum on a crop that has none system-wide; it can never remove
     * or raise a system maximum.
     */
    public static function tighterCeiling(?float $system, ?float $farm): ?float
    {
        if ($system === null) {
            return $farm;
        }

        return $farm === null ? $system : min($system, $farm);
    }

    /**
     * For the enforcement points that already hold a listing. Expects farm and
     * cropType to be loaded; see the eager-load note above.
     */
    public function forListing(Listing $listing): PriceGuard
    {
        return $this->for($listing->farm, $listing->cropType, $listing->unit);
    }

    /**
     * For a request that knows a farm id but holds no loaded Farm: a seller
     * creating a listing, or a request working from a listing's farm_id.
     *
     * A null id, or an id that no longer resolves, means no farm layer applies
     * and the system values stand. The constrained eager load is correct here:
     * exactly one crop type is being asked about.
     */
    public function forFarmId(int|string|null $farmId, CropType $cropType, ?ListingUnit $unit = null): PriceGuard
    {
        if ($farmId === null) {
            return $this->system($cropType, $unit);
        }

        $farm = Farm::query()
            ->with(['cropTypeOverrides' => fn ($query) => $query->where('crop_type_id', $cropType->getKey())])
            ->find($farmId);

        return $farm !== null
            ? $this->for($farm, $cropType, $unit)
            : $this->system($cropType, $unit);
    }

    /** The system values with no farm layer, for Super Admin system-wide views. */
    public function system(CropType $cropType, ?ListingUnit $unit = null): PriceGuard
    {
        return $this->combine($cropType, $cropType->unitGuardFor($unit), null);
    }

    /** The main values or the extra row's, tightened by the farm's override for that unit. */
    private function combine(CropType $cropType, ?CropTypeUnitGuard $row, ?FarmCropTypeOverride $override): PriceGuard
    {
        $source = $row ?? $cropType;
        $systemFloor = (float) $source->floor_price;

        return new PriceGuard(
            floor: max($systemFloor, (float) ($override?->floor_price ?? $systemFloor)),
            ceiling: self::tighterCeiling(
                $source->max_discount === null ? null : (float) $source->max_discount,
                $override?->max_discount === null ? null : (float) $override->max_discount,
            ),
            unit: $row?->unit ?? $cropType->unit_of_measure,
        );
    }
}
