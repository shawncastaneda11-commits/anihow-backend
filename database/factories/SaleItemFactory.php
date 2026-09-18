<?php

namespace Database\Factories;

use App\Enums\ListingUnit;
use App\Models\Listing;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'listing_id' => Listing::factory(),
            'listing_name' => 'Tomato',
            'unit' => ListingUnit::Kilogram,
            'quantity' => 1,
            'unit_price' => 65,
            'line_subtotal' => 65,
        ];
    }
}
