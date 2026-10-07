<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\FarmFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmFavorite>
 */
class FarmFavoriteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'farm_id' => Farm::factory(),
        ];
    }
}
