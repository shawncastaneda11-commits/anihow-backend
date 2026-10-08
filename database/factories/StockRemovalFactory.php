<?php

namespace Database\Factories;

use App\Enums\ListingUnit;
use App\Enums\StockRemovalReason;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\StockRemoval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockRemoval>
 */
class StockRemovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'farmer_seller_id' => User::factory(),
            'farm_id' => Farm::factory(),
            'crop_type_id' => CropType::factory(),
            'unit' => ListingUnit::Kilogram,
            'is_value_added' => false,
            'quantity' => 1,
            'reason' => StockRemovalReason::Spoiled,
            'note' => null,
            'price_per_unit' => 30,
            'recorded_by' => null,
        ];
    }
}
