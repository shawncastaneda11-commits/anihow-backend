<?php

namespace Database\Factories;

use App\Enums\ListingUnit;
use App\Models\Category;
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
            'category_id' => Category::factory(),
            'name' => fake()->randomElement(['Tomato', 'Ampalaya', 'Mango', 'Sitaw', 'Rice', 'Banana']),
            'unit' => fake()->randomElement(ListingUnit::cases()),
            'price_per_unit' => fake()->randomFloat(2, 20, 250),
            'quantity_available' => fake()->randomFloat(2, 5, 80),
            'description' => fake()->sentence(),
            'image_path' => null,
            'is_active' => true,
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
        return $this->state(fn (array $attributes) => [
            'farmer_seller_id' => $farmer->id,
        ]);
    }
}
