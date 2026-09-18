<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReviewResource;
use App\Http\Resources\Api\ShopProfileResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerShopController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $shops = User::query()
            ->role(Role::FarmerSeller->value)
            ->where('is_active', true)
            ->withCount('reviewsReceived')
            ->withAvg('reviewsReceived', 'rating')
            ->orderByRaw('coalesce(shop_name, name)')
            ->paginate();

        return ShopProfileResource::collection($shops);
    }

    public function show(User $farmerSeller): ShopProfileResource
    {
        abort_unless($farmerSeller->isFarmerSeller() && $farmerSeller->is_active, 404);

        $farmerSeller->load([
            'listings' => fn ($query) => $query->marketplaceVisible()->with('category')->latest(),
        ])
            ->loadCount('reviewsReceived')
            ->loadAvg('reviewsReceived', 'rating');

        $farmerSeller->listings->each(
            fn ($listing) => $listing->setRelation('farmerSeller', $farmerSeller),
        );

        return new ShopProfileResource($farmerSeller);
    }

    public function reviews(User $farmerSeller): AnonymousResourceCollection
    {
        abort_unless($farmerSeller->isFarmerSeller() && $farmerSeller->is_active, 404);

        $reviews = $farmerSeller->reviewsReceived()
            ->with('buyer')
            ->latest()
            ->orderByDesc('id')
            ->paginate();

        $farmerSeller->loadCount('reviewsReceived')->loadAvg('reviewsReceived', 'rating');

        return ReviewResource::collection($reviews)->additional([
            'average_rating' => $farmerSeller->averageRating(),
            'reviews_count' => (int) ($farmerSeller->reviews_received_count ?? 0),
        ]);
    }
}
