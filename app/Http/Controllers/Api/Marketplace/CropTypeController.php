<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Marketplace\CropTypeIndexRequest;
use App\Http\Resources\Api\CropTypeResource;
use App\Models\CropType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Buyers browse every active crop type. A farmer-seller sees their farm's
 * crop types once that farm has added its own. Until then they see the
 * shared catalog. A personal crop list, when present, narrows that further.
 */
class CropTypeController extends Controller
{
    public function __invoke(CropTypeIndexRequest $request): AnonymousResourceCollection
    {
        $seller = $request->user();

        $cropTypes = CropType::query()
            ->active()
            ->withCount([
                'listings as listings_count' => fn ($query) => $query->buyerVisible()->allowedByFarmFeatures(),
            ])
            ->when(
                $seller?->isFarmerSeller(),
                fn ($query) => $query->forFarm($seller->farm_id),
            )
            ->when(
                $seller?->isFarmerSeller() && $seller->farm !== null && ! $seller->farm->allowsValueAdded(),
                fn ($query) => $query->where('category', '!=', ProductCategory::ValueAdded->value),
            )
            ->when(
                $seller?->isFarmerSeller() && $seller->farmerCropTypes()->exists(),
                fn ($query) => $query->whereIn(
                    'crop_types.id',
                    $seller->farmerCropTypes()->select('crop_type_id'),
                ),
            )
            ->when(
                $request->validated('category'),
                fn ($query, string $category) => $query->where('category', $category),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CropTypeResource::collection($cropTypes);
    }
}
