<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'farmer_seller_id' => User::factory(),
            'status' => ReservationStatus::Pending,
            'total' => '0.00',
            'notes' => null,
        ];
    }
}
