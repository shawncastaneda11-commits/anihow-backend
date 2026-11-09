<?php

namespace Database\Factories;

use App\Enums\SellerRequestStatus;
use App\Models\Farm;
use App\Models\SellerRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerRequest>
 */
class SellerRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'requested_by' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'note' => null,
            'status' => SellerRequestStatus::Waiting,
        ];
    }
}
