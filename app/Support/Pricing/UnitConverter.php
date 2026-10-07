<?php

namespace App\Support\Pricing;

use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\FarmCropTypeOverride;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Converts a price or a quantity inside one unit family, and decides which
 * units a seller may choose for a crop type.
 *
 * Price guards stay in the crop type's unit. Callers convert first:
 * ₱0.06 per g is ₱60 per kg. Quantity analytics convert the other way,
 * into the family's base unit, so 500 g and 1 kg add up as 1.5 kg.
 */
class UnitConverter
{
    /**
     * @var list<string>
     */
    private const DISPLAY = [
        'g',
        'kg',
        'ml',
        'liter',
        'piece',
        'dozen',
        'tray',
        'bundle',
        'sack',
        'pack',
        'bottle',
    ];

    public function priceIn(?ListingUnit $from, ListingUnit $to, float|string $amount): float
    {
        $value = (float) $amount;

        if ($from === null || $from === $to) {
            return $value;
        }

        if (! $from->convertsTo($to)) {
            throw new InvalidArgumentException("Cannot convert {$from->value} to {$to->value}.");
        }

        return round($value * ($to->baseFactor() / $from->baseFactor()), 4);
    }

    /**
     * The listing price in the crop type's unit, or null when the units
     * cannot convert. Guard checks treat null as not allowed.
     */
    public function guardPrice(?ListingUnit $from, ListingUnit $to, float|string $amount): ?float
    {
        if ($from !== null && $from !== $to && ! $from->convertsTo($to)) {
            return null;
        }

        return $this->priceIn($from, $to, $amount);
    }

    public function accepts(CropType $cropType, ListingUnit $unit, ?int $farmId): bool
    {
        if (! $this->isGuarded($cropType, $farmId)) {
            return true;
        }

        return $cropType->unit_of_measure->convertsTo($unit);
    }

    public function refusalMessage(CropType $cropType, ?int $farmId): string
    {
        $cropUnit = $cropType->unit_of_measure;
        $allowed = collect($this->unitsInDisplayOrder())
            ->filter(fn (ListingUnit $unit): bool => $cropUnit->convertsTo($unit))
            ->map(fn (ListingUnit $unit): string => $unit->value)
            ->implode(' or ');
        $kind = $this->hasFloor($cropType, $farmId) ? 'floor price' : 'maximum discount';

        return "This crop's {$kind} is per {$cropUnit->value}, so it can be sold per {$allowed}.";
    }

    /**
     * @return list<array{value: string, label: string, family: string}>
     */
    public function allowedUnits(CropType $cropType, ?int $farmId): array
    {
        $cropUnit = $cropType->unit_of_measure;
        $guarded = $this->isGuarded($cropType, $farmId);

        return array_values(array_map(
            fn (ListingUnit $unit): array => [
                'value' => $unit->value,
                'label' => $unit->label(),
                'family' => $unit->family()->value,
            ],
            array_filter(
                $this->unitsInDisplayOrder(),
                fn (ListingUnit $unit): bool => ! $guarded || $cropUnit->convertsTo($unit),
            ),
        ));
    }

    /**
     * CASE expression. Multiply a quantity or divide a price by this factor
     * to move between a stored unit and its family base. Same numbers as baseFactor().
     */
    public static function sqlFactor(string $column): string
    {
        $cases = [];

        foreach (ListingUnit::cases() as $unit) {
            $cases[] = "WHEN {$column} = '{$unit->value}' THEN {$unit->baseFactorSql()}";
        }

        return 'CASE '.implode(' ', $cases).' ELSE 1 END';
    }

    /**
     * CASE expression for the family's base unit. Package units stay themselves.
     */
    public static function sqlBaseUnit(string $column): string
    {
        $cases = [];

        foreach (ListingUnit::cases() as $unit) {
            $cases[] = "WHEN {$column} = '{$unit->value}' THEN '{$unit->baseUnit()->value}'";
        }

        return 'CASE '.implode(' ', $cases)." ELSE {$column} END";
    }

    /**
     * True when the effective floor (already resolved in SQL) is above the
     * listing price after both are expressed in the crop type's unit.
     * Cross-multiplies so the comparison does not divide.
     */
    public static function belowFloorComparison(string $floorSql): string
    {
        $listingFactor = self::sqlFactor('listings.unit');
        $cropFactor = self::sqlFactor('crop_types.unit_of_measure');
        $converts = self::sqlConverts('listings.unit', 'crop_types.unit_of_measure');
        $numeric = '('.$floorSql.') * ('.$listingFactor.') > listings.price_per_unit * ('.$cropFactor.')';

        return "(({$converts}) AND ({$numeric})) OR (".self::incompatibleGuardSql().')';
    }

    /**
     * A guarded crop whose listing unit cannot convert into the crop unit.
     * Used by the below-floor filter and by the buyer catalogue.
     */
    public static function incompatibleGuardSql(): string
    {
        $converts = self::sqlConverts('listings.unit', 'crop_types.unit_of_measure');

        return '(NOT ('.$converts.')) AND ('.self::sqlGuarded().')';
    }

    public static function sqlConverts(string $listingUnit, string $cropUnit): string
    {
        $listingFamily = self::sqlFamily($listingUnit);
        $cropFamily = self::sqlFamily($cropUnit);

        return "(
            {$listingUnit} = {$cropUnit}
            OR (
                ({$listingFamily}) = ({$cropFamily})
                AND ({$listingFamily}) <> 'package'
            )
        )";
    }

    public static function sqlFamily(string $column): string
    {
        $cases = [];

        foreach (ListingUnit::cases() as $unit) {
            $cases[] = "WHEN {$column} = '{$unit->value}' THEN '{$unit->family()->value}'";
        }

        return 'CASE '.implode(' ', $cases)." ELSE '' END";
    }

    public static function sqlGuarded(): string
    {
        return <<<'SQL'
            (
                crop_types.floor_price > 0
                OR crop_types.max_discount > 0
                OR EXISTS (
                    SELECT 1 FROM farm_crop_type_overrides fo
                    WHERE fo.farm_id = listings.farm_id
                      AND fo.crop_type_id = listings.crop_type_id
                      AND (
                          COALESCE(fo.floor_price, 0) > 0
                          OR COALESCE(fo.max_discount, 0) > 0
                      )
                )
            )
            SQL;
    }

    public function checkoutRefusal(Listing $listing): string
    {
        $unit = $listing->unit?->value ?? 'its unit';
        $cropType = $listing->cropType;

        if ($cropType === null) {
            return "{$listing->title} is no longer available.";
        }

        return "{$listing->title} is sold per {$unit}, which cannot be checked against this crop's price. "
            .$this->refusalMessage($cropType, $listing->farm_id);
    }

    public function backfillListingUnits(): void
    {
        $units = DB::table('crop_types')->pluck('unit_of_measure', 'id');

        foreach ($units as $cropTypeId => $unit) {
            DB::table('listings')
                ->where('crop_type_id', $cropTypeId)
                ->update(['unit' => $unit]);
        }
    }

    public function isGuarded(CropType $cropType, ?int $farmId): bool
    {
        return $this->hasFloor($cropType, $farmId) || $this->hasMaximumDiscount($cropType, $farmId);
    }

    /**
     * @return list<ListingUnit>
     */
    private function unitsInDisplayOrder(): array
    {
        $byValue = [];

        foreach (ListingUnit::cases() as $unit) {
            $byValue[$unit->value] = $unit;
        }

        $ordered = [];

        foreach (self::DISPLAY as $value) {
            if (isset($byValue[$value])) {
                $ordered[] = $byValue[$value];
            }
        }

        return $ordered;
    }

    private function hasFloor(CropType $cropType, ?int $farmId): bool
    {
        if ((float) $cropType->floor_price > 0) {
            return true;
        }

        $override = $this->override($cropType, $farmId);

        return $override !== null && (float) ($override->floor_price ?? 0) > 0;
    }

    private function hasMaximumDiscount(CropType $cropType, ?int $farmId): bool
    {
        if ((float) $cropType->max_discount > 0) {
            return true;
        }

        $override = $this->override($cropType, $farmId);

        return $override !== null && (float) ($override->max_discount ?? 0) > 0;
    }

    private function override(CropType $cropType, ?int $farmId): ?FarmCropTypeOverride
    {
        if ($farmId === null) {
            return null;
        }

        return FarmCropTypeOverride::query()
            ->where('farm_id', $farmId)
            ->where('crop_type_id', $cropType->getKey())
            ->first();
    }
}
