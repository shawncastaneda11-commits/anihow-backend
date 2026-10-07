<?php

namespace App\Actions\Favorites;

use App\Models\ShopFavorite;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddShopFavoriteAction
{
    public function handle(User $buyer, User $farmerSeller): ShopFavorite
    {
        if (! $farmerSeller->isFarmerSeller() || ! $farmerSeller->isActive()) {
            throw ValidationException::withMessages([
                'farmer_seller_id' => 'That shop is not available.',
            ]);
        }

        return ShopFavorite::query()->firstOrCreate([
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $farmerSeller->id,
        ]);
    }
}
