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
            ->with($this->relations())
            ->when(
                $request->validated('crop_type_id'),
                fn (Builder $query, $cropTypeId) => $query->where('crop_type_id', $cropTypeId),
            )
            ->when(
                $request->validated('farm_id'),
                fn (Builder $query, $farmId) => $query->where('farm_id', $farmId),
            )
            ->when(
                $request->validated('search'),
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    // Search the seller's own title and the shared crop labels,
                    // so "tomato" and "kamatis" both find the same listings.
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('cropType', fn (Builder $cropType): Builder => $cropType
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('label_en', 'like', '%'.$search.'%')
                            ->orWhere('label_fil', 'like', '%'.$search.'%'));
                }),
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

        $listing->load($this->relations());

        return new ListingResource($listing);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function relations(): array
    {
        return [
            'cropType',
            'farm',
            'activeTawadRule',
            'farmerSeller' => function ($query): void {
                $query->withAvg(['reviewsReceived' => fn ($q) => $q->where('is_removed', false)], 'rating')
                    ->withCount(['reviewsReceived' => fn ($q) => $q->where('is_removed', false)]);
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
