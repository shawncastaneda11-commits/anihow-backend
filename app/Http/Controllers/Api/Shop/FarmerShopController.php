<?php

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\UpdateShopProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shop\UpdateShopProfileRequest;
use App\Http\Resources\Api\ShopProfileResource;
use App\Support\ShopReviews;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerShopController extends Controller
{
    public function show(Request $request): ShopProfileResource
    {
        $farmer = $request->user()->load('farm');

        return new ShopProfileResource(ShopReviews::loadStats($farmer));
    }

    public function update(UpdateShopProfileRequest $request, UpdateShopProfileAction $updateShop): ShopProfileResource
    {
        $farmer = $updateShop->handle($request->user(), $request->validated());
        $farmer->load('farm');

        return (new ShopProfileResource(ShopReviews::loadStats($farmer)))
            ->additional(['message' => 'Shop profile updated.']);
    }

    public function reviews(Request $request): AnonymousResourceCollection
    {
        return ShopReviews::collection($request->user());
    }
}
