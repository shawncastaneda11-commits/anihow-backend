<?php

namespace Tests\Feature;

use App\Enums\ListingUnit;
use App\Models\CropType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CropTypeDefaultUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_crop_type_created_without_a_unit_is_sold_by_the_kilogram(): void
    {
        $cropType = CropType::query()->create([
            'name' => 'Mais',
            'slug' => 'mais-default-unit',
            'label_en' => 'Corn',
            'label_fil' => 'Mais',
            'floor_price' => 8,
            'max_discount' => 3,
        ]);

        $this->assertSame(ListingUnit::Kilogram, $cropType->unit_of_measure);
    }
}
