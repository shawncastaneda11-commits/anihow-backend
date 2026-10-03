<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CropTypeResource;
use App\Models\CropType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Buyers browse every active crop type. A farmer-seller sees their farm's
 * crop types once that farm has added its own. Until then they see the
 * shared catalog. A personal crop list, when present, narrows that further.
 */
class CropTypeController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $seller = $request->user();

        $cropTypes = CropType::query()
            ->active()
            ->withCount([
                'listings as listings_count' => fn ($query) => $query->marketplaceVisible(),
            ])
            ->when(
                $seller?->isFarmerSeller(),
                fn ($query) => $query->forFarm($seller->farm_id),
            )
            ->when(
                $seller?->isFarmerSeller() && $seller->farmerCropTypes()->exists(),
                fn ($query) => $query->whereIn(
                    'crop_types.id',
                    $seller->farmerCropTypes()->select('crop_type_id'),
                ),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CropTypeResource::collection($cropTypes);
    }
}
