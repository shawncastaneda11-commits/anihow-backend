<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Marketplace\MarketplaceIndexRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketplaceController extends Controller
{
    public function index(MarketplaceIndexRequest $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->marketplaceVisible()
            ->with($this->marketplaceRelations())
            ->when(
                $request->validated('category_id'),
                fn (Builder $query, $categoryId) => $query->where('category_id', $categoryId),
            )
            ->when(
                $request->validated('search'),
                fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'),
            );

        $listings = $this->sorted($listings, $request->validated('sort') ?? 'freshest');

        return ListingResource::collection($listings->paginate());
    }

    public function show(Listing $listing): ListingResource
    {
        abort_unless(
            Listing::query()->marketplaceVisible()->whereKey($listing->id)->exists(),
            404,
        );

        $listing->load($this->marketplaceRelations());

        return new ListingResource($listing);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function marketplaceRelations(): array
    {
        return [
            'category',
            'farmerSeller' => function ($query): void {
                $query->withAvg('reviewsReceived', 'rating')
                    ->withCount('reviewsReceived');
            },
        ];
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    private function sorted(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price_per_unit')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price_per_unit')->orderBy('id'),
            'availability' => $query->orderByDesc('quantity_available')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
