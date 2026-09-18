<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'listing_id' => Listing::factory(),
        ];
    }
}
