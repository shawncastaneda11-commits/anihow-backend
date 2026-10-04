<?php

namespace App\Actions\Pricing;

use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Support\Pricing\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes the unit a crop type's floor price is quoted in.
 *
 * With no listings and no farm price overrides, any unit is allowed and the
 * stored peso amounts stay as typed. Once either exists, the unit may move
 * only inside the same family, and the floor, the maximum discount, and every
 * farm override are converted so the real price does not change: ₱50/kg
 * saved as grams becomes ₱0.0500/g, and ₱45/kg becomes ₱0.0450/g. Amounts
 * are stored to 4 decimal places.
 */
class ChangeCropTypeUnitAction
{
    public function __construct(
        private readonly UnitConverter $units,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CropType $cropType, array $attributes): CropType
    {
        return DB::transaction(function () use ($cropType, $attributes): CropType {
            $newUnit = $this->incomingUnit($cropType, $attributes);
            $oldUnit = $cropType->unit_of_measure;

            if ($newUnit !== $oldUnit && $this->isLocked($cropType) && ! $oldUnit->convertsTo($newUnit)) {
                throw ValidationException::withMessages([
                    'unit_of_measure' => $this->refusalMessage($cropType),
                ]);
            }

            if ($newUnit !== $oldUnit && $oldUnit->convertsTo($newUnit) && $this->isLocked($cropType)) {
                $attributes['floor_price'] = $this->convert(
                    $oldUnit,
                    $newUnit,
                    $attributes['floor_price'] ?? $cropType->floor_price,
                );
                $attributes['max_discount'] = $this->convert(
                    $oldUnit,
                    $newUnit,
                    $attributes['max_discount'] ?? $cropType->max_discount,
                );
                $this->convertOverrides($cropType, $oldUnit, $newUnit);
            }

            $attributes['unit_of_measure'] = $newUnit->value;
            $cropType->update($attributes);

            return $cropType->refresh();
        });
    }

    public function isLocked(CropType $cropType): bool
    {
        return $cropType->listings()->exists() || $cropType->farmOverrides()->exists();
    }

    public function refusalMessage(CropType $cropType): string
    {
        $allowed = collect(ListingUnit::cases())
            ->filter(fn (ListingUnit $unit): bool => $cropType->unit_of_measure->convertsTo($unit))
            ->map(fn (ListingUnit $unit): string => $unit->value)
            ->implode(' or ');

        return "This crop already has listings or a farm price, so its unit can only change to {$allowed}.";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function incomingUnit(CropType $cropType, array $attributes): ListingUnit
    {
        $value = $attributes['unit_of_measure'] ?? $cropType->unit_of_measure;

        if ($value instanceof ListingUnit) {
            return $value;
        }

        return ListingUnit::from((string) $value);
    }

    private function convert(ListingUnit $from, ListingUnit $to, mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return number_format($this->units->priceIn($from, $to, $amount), 4, '.', '');
    }

    private function convertOverrides(CropType $cropType, ListingUnit $from, ListingUnit $to): void
    {
        $cropType->farmOverrides()->each(function ($override) use ($from, $to): void {
            $override->update([
                'floor_price' => $this->convert($from, $to, $override->floor_price),
                'max_discount' => $this->convert($from, $to, $override->max_discount),
            ]);
        });
    }
}
