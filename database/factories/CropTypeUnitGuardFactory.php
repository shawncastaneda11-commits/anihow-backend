<?php

namespace Database\Factories;

use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropTypeUnitGuard>
 */
class CropTypeUnitGuardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crop_type_id' => CropType::factory(),
            'unit' => ListingUnit::Bundle,
            'floor_price' => 15.00,
            'max_discount' => 5.00,
        ];
    }
}
