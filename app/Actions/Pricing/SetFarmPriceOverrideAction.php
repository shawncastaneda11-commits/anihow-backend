<?php

namespace App\Actions\Pricing;

use App\Models\CropType;
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
 * The AtOrAboveSystemFloor and AtOrBelowSystemMaximum rules cover the
 * FormRequest surface. The checks repeated here are the last gate, for the
 * Filament panel, the console, and any future caller that does not come
 * through a request. Same reasoning as the max() and min() kept in
 * PriceGuardResolver.
 *
 * After the write, any listing newly stranded by a higher effective floor is
 * flagged to its seller. The override itself is never blocked by what it
 * strands. Decisions 9 and 14.
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
    ): ?FarmCropTypeOverride {
        $floorPrice = $this->normalize($floorPrice);
        $maxDiscount = $this->normalize($maxDiscount);

        $this->assertTightenOnly($cropType, $floorPrice, $maxDiscount);

        return DB::transaction(function () use ($farm, $cropType, $floorPrice, $maxDiscount): ?FarmCropTypeOverride {
            // Read the floor fresh, not from a relation loaded before this call.
            $farm->unsetRelation('cropTypeOverrides');
            $previousFloor = $this->resolver->for($farm, $cropType)->floor;

            $override = $this->write($farm, $cropType, $floorPrice, $maxDiscount);

            $farm->unsetRelation('cropTypeOverrides');
            $newFloor = $this->resolver->for($farm, $cropType)->floor;

            $this->flagStranded->execute($farm, $cropType, $previousFloor, $newFloor);

            return $override;
        });
    }

    private function write(
        Farm $farm,
        CropType $cropType,
        ?float $floorPrice,
        ?float $maxDiscount,
    ): ?FarmCropTypeOverride {
        // Null on both sides is the system value on both sides, which is what
        // the absence of a row already means. Delete rather than persist a
        // second encoding of one state.
        if ($floorPrice === null && $maxDiscount === null) {
            $farm->cropTypeOverrides()
                ->where('crop_type_id', $cropType->getKey())
                ->delete();

            return null;
        }

        return $farm->cropTypeOverrides()->updateOrCreate(
            ['crop_type_id' => $cropType->getKey()],
            ['floor_price' => $floorPrice, 'max_discount' => $maxDiscount],
        );
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
