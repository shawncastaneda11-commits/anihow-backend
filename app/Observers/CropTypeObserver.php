<?php

namespace App\Observers;

use App\Actions\Pricing\FlagStrandedListingsAction;
use App\Models\CropType;
use App\Models\Farm;
use App\Support\Pricing\PriceGuard;

/**
 * The system-layer caller of the stranded-listing flag. Until this existed, a
 * Super Admin raising a system floor stranded listings without telling anyone.
 * Decisions 14 and 16.
 *
 * Each farm is evaluated on its own effective values. A farm whose override
 * already sat beyond the new system value sees no change and is not notified.
 */
class CropTypeObserver
{
    public function __construct(
        private readonly FlagStrandedListingsAction $flagStranded,
    ) {}

    public function updated(CropType $cropType): void
    {
        // In the updated event getOriginal() still holds the pre-save values;
        // syncOriginal() runs after this event, not before it.
        $oldFloor = (float) $cropType->getOriginal('floor_price');
        $newFloor = (float) $cropType->floor_price;
        $oldCeiling = (float) $cropType->getOriginal('max_discount');
        $newCeiling = (float) $cropType->max_discount;

        $floorRaised = $cropType->wasChanged('floor_price')
            && PriceGuard::centavos($newFloor) > PriceGuard::centavos($oldFloor);

        $ceilingLowered = $cropType->wasChanged('max_discount')
            && PriceGuard::centavos($newCeiling) < PriceGuard::centavos($oldCeiling);

        if (! $floorRaised && ! $ceilingLowered) {
            return;
        }

        $cropTypeId = $cropType->getKey();

        Farm::query()
            ->whereHas('listings', fn ($query) => $query->where('crop_type_id', $cropTypeId))
            // A constrained eager load is right here, unlike in the resolver
            // note: exactly one crop type is in play for this whole pass.
            ->with(['cropTypeOverrides' => fn ($query) => $query->where('crop_type_id', $cropTypeId)])
            ->each(function (Farm $farm) use (
                $cropType, $cropTypeId, $oldFloor, $newFloor, $oldCeiling, $newCeiling, $floorRaised, $ceilingLowered,
            ): void {
                $override = $farm->overrideFor($cropTypeId);

                // PriceGuardResolver answers "what is the guard now". This needs
                // the guard before the save as well, and the crop type has
                // already changed, so the resolver's max() and min() are
                // applied to each side here. If that formula changes, change
                // this one too.
                $toldAboutFloor = [];

                if ($floorRaised) {
                    $farmFloor = $override?->floor_price;
                    $toldAboutFloor = $this->flagStranded->floorRaised(
                        $farm,
                        $cropType,
                        max($oldFloor, (float) ($farmFloor ?? $oldFloor)),
                        max($newFloor, (float) ($farmFloor ?? $newFloor)),
                    );
                }

                if ($ceilingLowered) {
                    $farmCeiling = $override?->max_discount;
                    $this->flagStranded->ceilingLowered(
                        $farm,
                        $cropType,
                        min($oldCeiling, (float) ($farmCeiling ?? $oldCeiling)),
                        min($newCeiling, (float) ($farmCeiling ?? $newCeiling)),
                        $toldAboutFloor,
                    );
                }
            });
    }
}
