<?php

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\StoreShopCoverAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shop\StoreShopCoverRequest;
use App\Http\Resources\Api\ShopProfileResource;
use App\Support\ShopReviews;
use Illuminate\Http\UploadedFile;

class StoreShopCoverController extends Controller
{
    public function __invoke(StoreShopCoverRequest $request, StoreShopCoverAction $storeCover): ShopProfileResource
    {
        $image = $request->file('image');
        abort_unless($image instanceof UploadedFile, 422);

        $farmer = $storeCover->handle($request->user(), $image);
        $farmer->load('farm');

        return new ShopProfileResource(ShopReviews::loadStats($farmer));
    }
}
