<?php

namespace App\Actions\Pricing;

use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Support\Pricing\PriceGuard;
use Illuminate\Validation\ValidationException;

/**
 * Writes a farm's tighten-only price guard for one crop type.
 *
 * Tighten-only means a farm floor at or above the system floor, and a farm
 * discount ceiling at or below the system maximum. Null on either side means
 * the system value applies.
 *
 * The AtOrAboveSystemFloor and AtOrBelowSystemMaximum rules cover the
 * FormRequest surface. The checks repeated here are the last gate, for the
 * Filament panel, the console, and any future caller that does not come
 * through a request. Same reasoning as the max() and min() kept in
 * PriceGuardResolver.
 *
 * Pass 2B part 2 hooks the listing flag pass onto the return of this action,
 * per decision 9: raising a farm floor flags every listing of that crop type
 * whose price, or whose price minus the largest applicable tawad, falls below
 * the new effective floor.
 */
class SetFarmPriceOverrideAction
{
    public function execute(
        Farm $farm,
        CropType $cropType,
        float|string|null $floorPrice = null,
        float|string|null $maxDiscount = null,
    ): ?FarmCropTypeOverride {
        $floorPrice = $this->normalize($floorPrice);
        $maxDiscount = $this->normalize($maxDiscount);

        $this->assertTightenOnly($cropType, $floorPrice, $maxDiscount);

        // Null on both sides is the system value on both sides, which is what
        // the absence of a row already means. Delete rather than persist a
        // second encoding of one state.
        if ($floorPrice === null && $maxDiscount === null) {
            $farm->cropTypeOverrides()
                ->where('crop_type_id', $cropType->getKey())
                ->delete();

            $farm->unsetRelation('cropTypeOverrides');

            return null;
        }

        $override = $farm->cropTypeOverrides()->updateOrCreate(
            ['crop_type_id' => $cropType->getKey()],
            ['floor_price' => $floorPrice, 'max_discount' => $maxDiscount],
        );

        $farm->unsetRelation('cropTypeOverrides');

        return $override;
    }

    private function assertTightenOnly(
        CropType $cropType,
        ?float $floorPrice,
        ?float $maxDiscount,
    ): void {
        $errors = [];

        if ($floorPrice !== null
            && PriceGuard::centavos($floorPrice) < PriceGuard::centavos($cropType->floor_price)) {
            $errors['floor_price'] = 'The farm floor price cannot be lower than the system floor of PHP '
                .number_format((float) $cropType->floor_price, 2).'.';
        }

        if ($maxDiscount !== null
            && PriceGuard::centavos($maxDiscount) > PriceGuard::centavos($cropType->max_discount)) {
            $errors['max_discount'] = 'The farm maximum peso discount cannot exceed the system maximum of PHP '
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
