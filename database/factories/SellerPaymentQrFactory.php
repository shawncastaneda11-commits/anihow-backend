<?php

namespace Database\Factories;

use App\Enums\PaymentWallet;
use App\Models\SellerPaymentQr;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerPaymentQr>
 */
class SellerPaymentQrFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_seller_id' => User::factory(),
            'wallet' => PaymentWallet::Gcash,
            'account_name' => fake()->name(),
            'account_last4' => (string) fake()->numerify('####'),
            'image_path' => 'payment-qrs/placeholder.jpg',
        ];
    }
}
