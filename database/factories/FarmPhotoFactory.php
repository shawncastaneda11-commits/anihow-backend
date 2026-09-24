<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\FarmPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmPhoto>
 */
class FarmPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'path' => 'farms/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional()->sentence(4),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
