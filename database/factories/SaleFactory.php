<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_seller_id' => User::factory(),
            'total' => '0.00',
            'notes' => null,
        ];
    }
}
