<?php

namespace Database\Factories;

use App\Models\StallConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StallConversation>
 */
class StallConversationFactory extends Factory
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
