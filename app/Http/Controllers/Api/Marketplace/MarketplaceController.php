<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Enums\GrowingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Marketplace\MarketplaceIndexRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;
use App\Support\FarmProximity;
use App\Support\Marketplace\FairMixOrdering;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketplaceController extends Controller
{
    /** The average rating a seller needs for the Top-rated sellers filter. */
    public const TOP_RATED = 4;

    public function index(MarketplaceIndexRequest $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->buyerVisible()
            ->allowedByFarmFeatures()
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
                $request->validated('category'),
                fn (Builder $query, string $category): Builder => $query->whereHas(
                    'cropType',
                    fn (Builder $cropType): Builder => $cropType->where('category', $category),
                ),
            )
            ->when(
                $request->validated('growing_method'),
                function (Builder $query, string $method): Builder {
                    $query->where('growing_method', $method);

                    if ($method === GrowingMethod::CertifiedOrganic->value) {
                        $query->whereHas(
                            'farm',
                            fn (Builder $farm): Builder => $farm->organicCertified(),
                        );
                    }

                    return $query;
                },
            )
            ->when(
                $request->boolean('in_stock'),
                fn (Builder $query): Builder => $query
                    ->whereRaw('listings.quantity_available - listings.quantity_held > 0')
                    ->where(fn (Builder $open): Builder => $open
                        ->whereNull('listings.available_from')
                        ->orWhere('listings.available_from', '<=', now())),
            )
            // Pre-order: harvests that have not opened yet, which buyers reserve.
            ->when(
                $request->boolean('upcoming'),
                fn (Builder $query): Builder => $query
                    ->whereNotNull('listings.available_from')
                    ->where('listings.available_from', '>', now()),
            )
            ->when(
                $request->boolean('online_payment'),
                fn (Builder $query): Builder => $query->whereHas(
                    'farmerSeller',
                    fn (Builder $seller): Builder => $seller
                        ->where('accepts_online_payment', true)
                        ->whereHas('paymentQrs'),
                ),
            )
            // A rule only counts while the farm has tawad switched on.
            ->when(
                $request->boolean('tawad'),
                fn (Builder $query): Builder => $query
                    ->whereHas('activeTawadRule')
                    ->where(fn (Builder $farm): Builder => $farm
                        ->whereNull('listings.farm_id')
                        ->orWhereHas('farm', fn (Builder $farms): Builder => $farms
                            ->where(fn (Builder $on): Builder => $on
                                ->whereNull('tawad_enabled')
                                ->orWhere('tawad_enabled', true)))),
            )
            ->when(
                $request->boolean('top_rated'),
                fn (Builder $query): Builder => $query->whereHas(
                    'farmerSeller',
                    fn (Builder $seller): Builder => $seller->whereRaw(
                        '(select avg(reviews.rating) from reviews where reviews.farmer_seller_id = users.id and reviews.is_removed = ?) >= ?',
                        [false, self::TOP_RATED],
                    ),
                ),
            )
            ->when(
                $request->validated('search'),
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    // Search the seller's own title and the shared crop labels,
                    // so "tomato" and "kamatis" both find the same listings.
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('cropType', fn (Builder $cropType): Builder => $cropType
                            ->where(function (Builder $cropType) use ($search): void {
                                $cropType->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('label_en', 'like', '%'.$search.'%')
                                    ->orWhere('label_fil', 'like', '%'.$search.'%');
                            }))
                        ->orWhereHas('farm', fn (Builder $farm): Builder => $farm
                            ->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('farmerSeller', fn (Builder $seller): Builder => $seller
                            ->where(function (Builder $seller) use ($search): void {
                                $seller->where('shop_name', 'like', '%'.$search.'%')
                                    ->orWhere('name', 'like', '%'.$search.'%');
                            }));
                }),
            );

        $sort = $request->validated('sort') ?? 'fair';
        $ordering = app(FairMixOrdering::class);
        $mixDay = $sort === 'fair'
            ? $ordering->day($request->validated('mix_day'))
            : null;

        $listings = $this->sorted(
            $listings,
            $sort,
            $this->near($request->validated('near_lat')),
            $this->near($request->validated('near_lng')),
            $mixDay,
        );

        $page = ListingResource::collection(
            $listings->paginate($request->integer('per_page', 15)),
        );

        if ($mixDay !== null) {
            $page->additional(['meta' => ['mix_day' => $mixDay]]);
        }

        return $page;
    }

    public function show(Listing $listing): ListingResource
    {
        $visible = Listing::query()
            ->buyerVisible()
            ->allowedByFarmFeatures()
            ->with($this->relations())
            ->find($listing->id);

        abort_if($visible === null, 404);

        return new ListingResource($visible);
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
    private function sorted(Builder $query, string $sort, ?float $nearLat, ?float $nearLng, ?string $mixDay): Builder
    {
        if ($sort === 'nearest') {
            return FarmProximity::apply($query, 'listings.farm_id', $nearLat, $nearLng);
        }

        return match ($sort) {
            'fair' => app(FairMixOrdering::class)->apply($query, $mixDay),
            'price_asc' => $query->orderBy('price_per_unit')->orderBy('listings.id'),
            'price_desc' => $query->orderByDesc('price_per_unit')->orderBy('listings.id'),
            'availability' => $query->orderByDesc('quantity_available')->orderBy('listings.id'),
            'freshest' => $query->orderByDesc('listings.created_at')->orderByDesc('listings.id'),
            default => $query->orderByDesc('listings.created_at')->orderByDesc('listings.id'),
        };
    }

    private function near(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
