<?php

namespace Database\Factories;

use App\Models\ShopFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopFavorite>
 */
class ShopFavoriteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'farmer_seller_id' => User::factory(),
        ];
    }
}
