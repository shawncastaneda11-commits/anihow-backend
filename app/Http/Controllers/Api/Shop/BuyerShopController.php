<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ShopProfileResource;
use App\Models\User;
use App\Support\ShopReviews;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerShopController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $shops = User::query()
            ->role(Role::FarmerSeller->value)
            ->where('status', UserStatus::Active)
            ->withCount(ShopReviews::receivedAggregate())
            ->withAvg(ShopReviews::receivedAggregate(), 'rating')
            ->orderByRaw('coalesce(shop_name, name)')
            ->paginate();

        return ShopProfileResource::collection($shops);
    }

    public function show(User $farmerSeller): ShopProfileResource
    {
        abort_unless($farmerSeller->isFarmerSeller() && $farmerSeller->isActive(), 404);

        $farmerSeller->load([
            'farm',
            'listings' => fn ($query) => $query
                ->marketplaceVisible()
                ->with(['cropType', 'farm', 'activeTawadRule'])
                ->latest(),
        ]);

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
