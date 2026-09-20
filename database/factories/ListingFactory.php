<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_seller_id' => User::factory(),
            'farm_id' => Farm::factory(),
            'crop_type_id' => CropType::factory(),
            'title' => fake()->randomElement([
                'Fresh kamatis, hand picked',
                'Kamatis, bagong ani',
                'Morning harvest kamote',
            ]),
            'description' => fake()->sentence(),
            'price_per_unit' => 30.00,
            'quantity_available' => 50.00,
            'quantity_held' => 0,
            'image_path' => null,
            'is_active' => true,
            'status' => ListingStatus::Published,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forFarmer(User $farmer): static
    {
        return $this->state(function () use ($farmer): array {
            $farmId = $farmer->farm_id;

            if ($farmId === null) {
                $farmId = Farm::factory()->create()->id;
                $farmer->forceFill(['farm_id' => $farmId])->save();
            }

            return [
                'farmer_seller_id' => $farmer->id,
                'farm_id' => $farmId,
            ];
        });
    }
}
