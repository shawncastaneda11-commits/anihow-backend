<?php

namespace Database\Factories;

use App\Enums\TawadType;
use App\Models\Listing;
use App\Models\TawadRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TawadRule>
 */
class TawadRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'type' => TawadType::MinimumQuantity,
            'discount_amount' => 12.00,
            'min_quantity' => 4.00,
            'is_active' => true,
            'ended_at' => null,
        ];
    }

    public function flat(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TawadType::Flat,
            'min_quantity' => null,
            'discount_amount' => 5.00,
        ]);
    }
}
