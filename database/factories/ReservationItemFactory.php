<?php

namespace Database\Factories;

use App\Enums\ListingUnit;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationItem>
 */
class ReservationItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'listing_id' => Listing::factory(),
            'listing_name' => 'Tomato',
            'unit' => ListingUnit::Kilogram,
            'quantity' => 1,
            'unit_price' => 65,
            'line_subtotal' => 65,
        ];
    }
}
