<?php

namespace Database\Factories;

use App\Enums\HarvestRecordKind;
use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestRecord>
 */
class HarvestRecordFactory extends Factory
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
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => 10,
            'quantity_rejected' => 0,
            'quantity_good' => 10,
            'rejection_reason' => null,
            'rejection_note' => null,
            'price_per_unit' => 30,
            'production_cost' => null,
            'cost_breakdown' => null,
            'kind' => HarvestRecordKind::Initial,
            'recorded_by' => null,
        ];
    }
}
