<?php

namespace Database\Factories;

use App\Enums\ListingUnit;
use App\Models\CropType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CropType>
 */
class CropTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'label_en' => 'Tomato',
            'label_fil' => 'Kamatis',
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => 25.00,
            'max_discount' => 20.00,
            'is_active' => true,
        ];
    }
}
