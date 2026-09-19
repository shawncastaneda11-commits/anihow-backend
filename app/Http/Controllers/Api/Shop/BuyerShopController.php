<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReviewResource;
use App\Http\Resources\Api\ShopProfileResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerShopController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $shops = User::query()
            ->role(Role::FarmerSeller->value)
            ->where('status', UserStatus::Active)
            ->withCount($this->visibleReviews())
            ->withAvg($this->visibleReviews(), 'rating')
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
        ])
            ->loadCount($this->visibleReviews())
            ->loadAvg($this->visibleReviews(), 'rating');

        $farmerSeller->listings->each(
            fn ($listing) => $listing->setRelation('farmerSeller', $farmerSeller),
        );

        return new ShopProfileResource($farmerSeller);
    }

    public function reviews(User $farmerSeller): AnonymousResourceCollection
    {
        abort_unless($farmerSeller->isFarmerSeller() && $farmerSeller->isActive(), 404);

        $reviews = $farmerSeller->reviewsReceived()
            ->visible()
            ->with('buyer')
            ->latest()
            ->orderByDesc('id')
            ->paginate();

        $farmerSeller
            ->loadCount($this->visibleReviews())
            ->loadAvg($this->visibleReviews(), 'rating');

        return ReviewResource::collection($reviews)->additional([
            'average_rating' => $farmerSeller->averageRating(),
            'reviews_count' => (int) ($farmerSeller->reviews_received_count ?? 0),
        ]);
    }

    /**
     * A review the Super Admin removed must not count toward a seller's
     * rating or appear in their review list.
     *
     * @return array<string, callable>
     */
    private function visibleReviews(): array
    {
        return ['reviewsReceived' => fn (Builder $query): Builder => $query->where('is_removed', false)];
    }
}
