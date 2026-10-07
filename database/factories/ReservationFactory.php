<?php

namespace Database\Factories;

use App\Enums\FulfillmentPreference;
use App\Enums\ListingUnit;
use App\Enums\ReservationStatus;
use App\Models\Farm;
use App\Models\Listing;
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
            'listing_id' => Listing::factory(),
            'farmer_seller_id' => User::factory(),
            'farm_id' => Farm::factory(),
            'quantity' => 1,
            'unit' => ListingUnit::Kilogram,
            'unit_price' => 30,
            'line_subtotal' => 30,
            'tawad_amount' => 0,
            'line_total' => 30,
            'listing_name' => 'Reserved harvest',
            'fulfillment_preference' => FulfillmentPreference::BuyerPickup,
            'status' => ReservationStatus::Active,
            'active_slot' => null,
        ];
    }
}
