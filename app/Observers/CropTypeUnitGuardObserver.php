<?php

namespace App\Observers;

use App\Actions\Pricing\FlagStrandedListingsAction;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Support\Pricing\PriceGuard;
use App\Support\Pricing\PriceGuardResolver;

/**
 * Tells sellers when a change to one of a crop's extra unit rows strands their
 * listing. The CropTypeObserver counterpart for these rows.
 *
 * created   listings in the row's unit were refused before; they are now
 *           checked against the new floor and maximum, so it is treated as a
 *           change from no limits
 * updated   a raised floor or a lowered maximum
 * deleted   the farms' overrides for that unit go too, and listings in that
 *           unit are no longer allowed
 *
 * Each farm is evaluated on its own effective values: the row tightened by
 * the farm's override for that unit.
 */
class CropTypeUnitGuardObserver
{
    public function __construct(
        private readonly FlagStrandedListingsAction $flagStranded,
    ) {}

    public function created(CropTypeUnitGuard $row): void
    {
        $this->flag($row, 0.0, (float) $row->floor_price, null, $this->ceiling($row->max_discount), true, $row->max_discount !== null);
    }

    public function updated(CropTypeUnitGuard $row): void
    {
        // In the updated event getOriginal() still holds the pre-save values.
        $oldFloor = (float) $row->getOriginal('floor_price');
        $newFloor = (float) $row->floor_price;
        $oldCeiling = $this->ceiling($row->getOriginal('max_discount'));
        $newCeiling = $this->ceiling($row->max_discount);

        $this->flag(
            $row,
            $oldFloor,
            $newFloor,
            $oldCeiling,
            $newCeiling,
            $row->wasChanged('floor_price') && PriceGuard::centavos($newFloor) > PriceGuard::centavos($oldFloor),
            $row->wasChanged('max_discount') && PriceGuard::ceilingTightened($oldCeiling, $newCeiling),
        );
    }

    public function deleted(CropTypeUnitGuard $row): void
    {
        FarmCropTypeOverride::query()
            ->where('crop_type_id', $row->crop_type_id)
            ->where('unit', $row->unit->value)
            ->delete();

        $cropType = CropType::query()->with('unitGuards')->find($row->crop_type_id);

        if ($cropType === null) {
            return;
        }

        Farm::query()
            ->whereHas('listings', fn ($query) => $query->where('crop_type_id', $cropType->getKey()))
            ->each(fn (Farm $farm) => $this->flagStranded->incompatibleUnits($farm, $cropType));
    }

    private function flag(
        CropTypeUnitGuard $row,
        float $oldFloor,
        float $newFloor,
        ?float $oldCeiling,
        ?float $newCeiling,
        bool $floorRaised,
        bool $ceilingLowered,
    ): void {
        if (! $floorRaised && ! $ceilingLowered) {
            return;
        }

        $cropType = CropType::query()->with('unitGuards')->find($row->crop_type_id);

        if ($cropType === null) {
            return;
        }

        $unit = $row->unit;

        Farm::query()
            ->whereHas('listings', fn ($query) => $query->where('crop_type_id', $cropType->getKey()))
            ->with(['cropTypeOverrides' => fn ($query) => $query->where('crop_type_id', $cropType->getKey())])
            ->each(function (Farm $farm) use ($cropType, $unit, $oldFloor, $newFloor, $oldCeiling, $newCeiling, $floorRaised, $ceilingLowered): void {
                $override = $farm->overrideFor($cropType->getKey(), $unit);
                $toldAboutFloor = [];

                // Same max() and min() as PriceGuardResolver, applied to the
                // values before and after the save.
                if ($floorRaised) {
                    $farmFloor = $override?->floor_price;
                    $toldAboutFloor = $this->flagStranded->floorRaised(
                        $farm,
                        $cropType,
                        max($oldFloor, (float) ($farmFloor ?? $oldFloor)),
                        max($newFloor, (float) ($farmFloor ?? $newFloor)),
                        $unit,
                    );
                }

                if ($ceilingLowered) {
                    $farmCeiling = $this->ceiling($override?->max_discount);
                    $this->flagStranded->ceilingLowered(
                        $farm,
                        $cropType,
                        PriceGuardResolver::tighterCeiling($oldCeiling, $farmCeiling),
                        PriceGuardResolver::tighterCeiling($newCeiling, $farmCeiling),
                        $toldAboutFloor,
                        $unit,
                    );
                }
            });
    }

    private function ceiling(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
