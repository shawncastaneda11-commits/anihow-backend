<?php

namespace Database\Factories;

use App\Enums\PaymentProofStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'buyer_id' => User::factory(),
            'farmer_seller_id' => User::factory(),
            'seller_payment_qr_id' => null,
            'reference_number' => strtoupper(fake()->bothify('??????####')),
            'amount' => 30,
            'screenshot_path' => null,
            'status' => PaymentProofStatus::Pending,
        ];
    }
}
