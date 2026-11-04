<?php

namespace App\Actions\Pricing;

use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Support\Pricing\PriceGuard;
use App\Support\Pricing\PriceGuardResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes a farm's tighten-only price guard for one crop type.
 *
 * Tighten-only means a farm floor at or above the system floor, and a farm
 * discount ceiling at or below the system maximum. Null on either side means
 * the system value applies.
 *
 * $unit picks which of the crop's guards the override tightens: null (or any
 * unit in the main unit's family) for the main unit, or the unit of one of the
 * crop's extra rows.
 *
 * Tighten-only is enforced here and nowhere else. The panel form does not
 * repeat these bounds, and there is no second rule on a request. Same
 * reasoning as the max() and min() kept in PriceGuardResolver.
 *
 * After the write, any listing newly stranded by a higher effective floor or a
 * lower effective ceiling is flagged to its seller. The override itself is
 * never blocked by what it strands. Decisions 9, 14, and 16.
 */
class SetFarmPriceOverrideAction
{
    public function __construct(
        private readonly PriceGuardResolver $resolver,
        private readonly FlagStrandedListingsAction $flagStranded,
    ) {}

    public function execute(
        Farm $farm,
        CropType $cropType,
        float|string|null $floorPrice = null,
        float|string|null $maxDiscount = null,
        ?ListingUnit $unit = null,
    ): ?FarmCropTypeOverride {
        $floorPrice = $this->normalize($floorPrice);
        $maxDiscount = $this->normalize($maxDiscount);
        $row = $this->unitRow($cropType, $unit);
        $unit = $row?->unit;

        $this->assertTightenOnly($row ?? $cropType, $floorPrice, $maxDiscount);

        return DB::transaction(function () use ($farm, $cropType, $floorPrice, $maxDiscount, $unit): ?FarmCropTypeOverride {
            // Read the guard fresh, not from a relation loaded before this call.
            $farm->unsetRelation('cropTypeOverrides');
            $before = $this->resolver->for($farm, $cropType, $unit);

            $override = $this->write($farm, $cropType, $floorPrice, $maxDiscount, $unit);

            $farm->unsetRelation('cropTypeOverrides');
            $after = $this->resolver->for($farm, $cropType, $unit);

            $toldAboutFloor = $this->flagStranded->floorRaised($farm, $cropType, $before->floor, $after->floor, $unit);
            $this->flagStranded->ceilingLowered($farm, $cropType, $before->ceiling, $after->ceiling, $toldAboutFloor, $unit);

            $wasGuarded = $before->floor > 0 || (float) $before->ceiling > 0;
            $nowGuarded = $after->floor > 0 || (float) $after->ceiling > 0;

            if ($unit === null && ! $wasGuarded && $nowGuarded) {
                $this->flagStranded->incompatibleUnits($farm, $cropType);
            }

            return $override;
        });
    }

    private function write(
        Farm $farm,
        CropType $cropType,
        ?float $floorPrice,
        ?float $maxDiscount,
        ?ListingUnit $unit,
    ): ?FarmCropTypeOverride {
        // Null on both sides is the system value on both sides, which is what
        // the absence of a row already means. Delete rather than persist a
        // second encoding of one state.
        if ($floorPrice === null && $maxDiscount === null) {
            $farm->cropTypeOverrides()
                ->where('crop_type_id', $cropType->getKey())
                ->when(
                    $unit === null,
                    fn ($query) => $query->whereNull('unit'),
                    fn ($query) => $query->where('unit', $unit?->value),
                )
                ->delete();

            return null;
        }

        return $farm->cropTypeOverrides()->updateOrCreate(
            ['crop_type_id' => $cropType->getKey(), 'unit' => $unit?->value],
            ['floor_price' => $floorPrice, 'max_discount' => $maxDiscount],
        );
    }

    /**
     * The extra row an override for this unit tightens, or null for the main
     * unit. A unit the crop has no limits for is refused.
     */
    private function unitRow(CropType $cropType, ?ListingUnit $unit): ?CropTypeUnitGuard
    {
        if ($unit === null || $cropType->unit_of_measure->convertsTo($unit)) {
            return null;
        }

        $cropType->unsetRelation('unitGuards');
        $row = $cropType->unitGuards->first(fn (CropTypeUnitGuard $row): bool => $row->unit === $unit);

        if ($row === null) {
            throw ValidationException::withMessages([
                'unit' => "{$cropType->name} has no price limits per {$unit->value}. Add them to the crop first.",
            ]);
        }

        return $row;
    }

    private function assertTightenOnly(
        CropType|CropTypeUnitGuard $cropType,
        ?float $floorPrice,
        ?float $maxDiscount,
    ): void {
        $errors = [];

        if ($floorPrice !== null
            && PriceGuard::centavos($floorPrice) < PriceGuard::centavos($cropType->floor_price)) {
            $errors['floor_price'] = 'The farm floor price cannot be lower than the system floor of PHP '
                .number_format((float) $cropType->floor_price, 2).'.';
        }

        // A crop with no system maximum takes any farm maximum: setting one
        // only tightens.
        if ($maxDiscount !== null
            && $cropType->max_discount !== null
            && PriceGuard::centavos($maxDiscount) > PriceGuard::centavos($cropType->max_discount)) {
            $errors['max_discount'] = 'The farm maximum tawad cannot exceed the system maximum of PHP '
                .number_format((float) $cropType->max_discount, 2).'.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalize(float|string|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
