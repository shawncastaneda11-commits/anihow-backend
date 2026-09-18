<?php

namespace App\Actions\Favorites;

use App\Models\Favorite;
use App\Models\Listing;
use App\Models\User;

class AddFavoriteAction
{
    public function handle(User $buyer, Listing $listing): Favorite
    {
        return Favorite::query()->firstOrCreate([
            'buyer_id' => $buyer->id,
            'listing_id' => $listing->id,
        ])->load('listing');
    }
}
