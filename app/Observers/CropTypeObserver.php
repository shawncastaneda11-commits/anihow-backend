<?php

namespace App\Observers;

use App\Actions\Pricing\FlagStrandedListingsAction;
use App\Models\CropType;
use App\Models\Farm;
use App\Support\Pricing\PriceGuard;

/**
 * The first caller of InAppNotifier::floorPriceRaised(). Until this existed, a
 * Super Admin raising a system floor stranded listings without telling anyone.
 * Decision 14.
 *
 * Each farm is evaluated on its own effective floor. A farm whose override
 * already sat above the new system floor sees no change and is not notified.
 */
class CropTypeObserver
{
    public function __construct(
        private readonly FlagStrandedListingsAction $flagStranded,
    ) {}

    public function updated(CropType $cropType): void
    {
        if (! $cropType->wasChanged('floor_price')) {
            return;
        }

        // In the updated event getOriginal() still holds the pre-save value;
        // syncOriginal() runs after this event, not before it.
        $oldSystemFloor = (float) $cropType->getOriginal('floor_price');
        $newSystemFloor = (float) $cropType->floor_price;

        if (PriceGuard::centavos($newSystemFloor) <= PriceGuard::centavos($oldSystemFloor)) {
            return;
        }

        $cropTypeId = $cropType->getKey();

        Farm::query()
            ->whereHas('listings', fn ($query) => $query->where('crop_type_id', $cropTypeId))
            // A constrained eager load is right here, unlike in the resolver
            // note: exactly one crop type is in play for this whole pass.
            ->with(['cropTypeOverrides' => fn ($query) => $query->where('crop_type_id', $cropTypeId)])
            ->each(function (Farm $farm) use ($cropType, $cropTypeId, $oldSystemFloor, $newSystemFloor): void {
                $farmFloor = $farm->overrideFor($cropTypeId)?->floor_price;

                // PriceGuardResolver answers "what is the floor now". This needs
                // the floor before the save as well, and the crop type has
                // already changed, so the same max() is applied to each side.
                $previous = max($oldSystemFloor, (float) ($farmFloor ?? $oldSystemFloor));
                $new = max($newSystemFloor, (float) ($farmFloor ?? $newSystemFloor));

                $this->flagStranded->execute($farm, $cropType, $previous, $new);
            });
    }
}
