<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shop\BuyerShopIndexRequest;
use App\Http\Resources\Api\ShopProfileResource;
use App\Models\User;
use App\Support\FarmProximity;
use App\Support\ShopReviews;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerShopController extends Controller
{
    public function index(BuyerShopIndexRequest $request): AnonymousResourceCollection
    {
        $shops = User::query()
            ->role(Role::FarmerSeller->value)
            ->where('status', UserStatus::Active)
            ->with('farm')
            ->withCount(ShopReviews::receivedAggregate())
            ->withAvg(ShopReviews::receivedAggregate(), 'rating')
            ->when(
                $request->user() !== null,
                fn ($query) => $query->withExists([
                    'shopFans as is_favorited' => fn ($favorites) => $favorites
                        ->where('buyer_id', $request->user()->id),
                ]),
            );

        if ($request->validated('sort') === 'nearest') {
            $lat = $request->validated('near_lat');
            $lng = $request->validated('near_lng');
            FarmProximity::apply(
                $shops,
                'users.farm_id',
                is_numeric($lat) ? (float) $lat : null,
                is_numeric($lng) ? (float) $lng : null,
            );
        } else {
            $shops->orderByRaw('coalesce(shop_name, name)');
        }

        $shops = $shops->paginate();

        return ShopProfileResource::collection($shops);
    }

    public function show(Request $request, User $farmerSeller): ShopProfileResource
    {
        abort_unless($farmerSeller->isFarmerSeller() && $farmerSeller->isActive(), 404);

        $farmerSeller->load([
            'farm',
            'listings' => fn ($query) => $query
                ->buyerVisible()
                ->with(['cropType', 'farm', 'activeTawadRule'])
                ->latest(),
        ]);

        if ($request->user() !== null) {
            $farmerSeller->loadExists([
                'shopFans as is_favorited' => fn ($favorites) => $favorites
                    ->where('buyer_id', $request->user()->id),
            ]);
        }

        ShopReviews::loadStats($farmerSeller);

        $farmerSeller->listings->each(
            fn ($listing) => $listing->setRelation('farmerSeller', $farmerSeller),
        );

        return new ShopProfileResource($farmerSeller);
    }

    public function reviews(User $farmerSeller): AnonymousResourceCollection
    {
        abort_unless($farmerSeller->isFarmerSeller() && $farmerSeller->isActive(), 404);

        return ShopReviews::collection($farmerSeller);
    }
}
