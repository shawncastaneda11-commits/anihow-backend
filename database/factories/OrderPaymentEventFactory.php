<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderPaymentEvent>
 */
class OrderPaymentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'event' => 'placed_online',
            'actor_id' => null,
            'note' => null,
            'created_at' => now(),
        ];
    }
}
