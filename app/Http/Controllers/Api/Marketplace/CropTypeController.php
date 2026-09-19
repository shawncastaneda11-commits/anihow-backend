<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CropTypeResource;
use App\Models\CropType;
use App\Models\Listing;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The shared taxonomy, browsable by every actor. Replaces the old flat
 * category list: an entry here carries bilingual labels, the unit of measure,
 * and the floor price that every listing under it must respect.
 */
class CropTypeController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $cropTypes = CropType::query()
            ->active()
            ->withCount([
                'listings as listings_count' => fn ($query) => $query->marketplaceVisible(),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return CropTypeResource::collection($cropTypes);
    }
}
