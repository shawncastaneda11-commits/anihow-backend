<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'farmer_seller_id' => User::factory(),
            'reservation_id' => Reservation::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
