<?php

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\DeleteShopCoverAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ShopProfileResource;
use App\Support\ShopReviews;
use Illuminate\Http\Request;

class DeleteShopCoverController extends Controller
{
    public function __invoke(Request $request, DeleteShopCoverAction $deleteCover): ShopProfileResource
    {
        abort_unless($request->user()?->isFarmerSeller() ?? false, 403);

        $farmer = $deleteCover->handle($request->user());
        $farmer->load('farm');

        return new ShopProfileResource(ShopReviews::loadStats($farmer));
    }
}
