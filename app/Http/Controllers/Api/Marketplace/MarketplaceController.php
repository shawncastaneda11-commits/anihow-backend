<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Marketplace\MarketplaceIndexRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketplaceController extends Controller
{
    public function index(MarketplaceIndexRequest $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->marketplaceVisible()
            ->with(['category', 'farmerSeller'])
            ->when(
                $request->validated('category_id'),
                fn ($query, $categoryId) => $query->where('category_id', $categoryId),
            )
            ->when(
                $request->validated('search'),
                fn ($query, string $search) => $query->where('name', 'like', '%'.$search.'%'),
            )
            ->latest()
            ->paginate();

        return ListingResource::collection($listings);
    }

    public function show(Listing $listing): ListingResource
    {
        abort_unless(
            Listing::query()->marketplaceVisible()->whereKey($listing->id)->exists(),
            404,
        );

        $listing->load(['category', 'farmerSeller']);

        return new ListingResource($listing);
    }
}
