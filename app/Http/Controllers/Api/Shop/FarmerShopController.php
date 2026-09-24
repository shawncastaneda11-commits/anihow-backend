<?php

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\UpdateShopProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shop\UpdateShopProfileRequest;
use App\Http\Resources\Api\ShopProfileResource;
use Illuminate\Http\Request;

class FarmerShopController extends Controller
{
    public function show(Request $request): ShopProfileResource
    {
        $farmer = $request->user()
            ->load('farm')
            ->loadCount('reviewsReceived')
            ->loadAvg('reviewsReceived', 'rating');

        return new ShopProfileResource($farmer);
    }

    public function update(UpdateShopProfileRequest $request, UpdateShopProfileAction $updateShop): ShopProfileResource
    {
        $farmer = $updateShop->handle($request->user(), $request->validated());
        $farmer->load('farm')->loadCount('reviewsReceived')->loadAvg('reviewsReceived', 'rating');

        return (new ShopProfileResource($farmer))
            ->additional(['message' => 'Shop profile updated.']);
    }
}
