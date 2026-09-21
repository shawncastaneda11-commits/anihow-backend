<?php

namespace App\Http\Resources\Api;

use App\Models\CropType;
use App\Models\Farm;
use App\Support\Pricing\PriceGuard;
use App\Support\Pricing\PriceGuardResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CropType */
class CropTypeResource extends JsonResource
{
    /**
     * Request attribute holding the requesting user's farm, loaded once with its
     * overrides so a list of crop types resolves without a query per row. Kept on
     * the request, not in a static, so it can never leak between requests.
     */
    private const FARM_KEY = 'anihow.price_guard_farm';

    /**
     * floor_price and max_discount are the system values, unchanged, so existing
     * clients keep working.
     *
     * effective_floor_price and effective_max_discount are the values the
     * requesting user's farm is actually held to: the system values, tightened by
     * the farm where it has tightened them. A user with no farm, such as a buyer
     * or the Super Admin, gets the system values in both. Decision 18.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $guard = $this->guardFor($request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'label_en' => $this->label_en,
            'label_fil' => $this->label_fil,
            'unit_of_measure' => $this->unit_of_measure->value,
            'unit_label' => $this->unit_of_measure->label(),
            'floor_price' => (float) $this->floor_price,
            'max_discount' => (float) $this->max_discount,
            'effective_floor_price' => $guard->floor,
            'effective_max_discount' => $guard->ceiling,
            'listings_count' => $this->whenCounted('listings'),
        ];
    }

    private function guardFor(Request $request): PriceGuard
    {
        $resolver = app(PriceGuardResolver::class);
        $farmId = $request->user()?->farm_id;

        if ($farmId === null) {
            return $resolver->system($this->resource);
        }

        $farm = $request->attributes->get(self::FARM_KEY);

        if (! $farm instanceof Farm || (int) $farm->getKey() !== (int) $farmId) {
            $farm = Farm::query()->with('cropTypeOverrides')->find($farmId);
            $request->attributes->set(self::FARM_KEY, $farm);
        }

        return $farm instanceof Farm
            ? $resolver->for($farm, $this->resource)
            : $resolver->system($this->resource);
    }
}
