<?php

namespace Database\Seeders;

use App\Enums\ListingUnit;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $juan = User::query()->where('email', 'juan@anihow.local')->firstOrFail();
        $maria = User::query()->where('email', 'maria.santos@anihow.local')->firstOrFail();
        $pedro = User::query()->where('email', 'pedro.reyes@anihow.local')->firstOrFail();

        $vegetables = Category::query()->where('slug', 'vegetables')->firstOrFail();
        $fruit = Category::query()->where('slug', 'fruit')->firstOrFail();
        $grains = Category::query()->where('slug', 'grains')->firstOrFail();
        $roots = Category::query()->where('slug', 'root-crops')->firstOrFail();
        $herbs = Category::query()->where('slug', 'herbs')->firstOrFail();

        $listings = [
            [
                'farmer_seller_id' => $juan->id,
                'category_id' => $vegetables->id,
                'name' => 'Ampalaya',
                'unit' => ListingUnit::Kilogram,
                'price_per_unit' => 80,
                'quantity_available' => 25,
                'description' => 'Fresh bitter gourd harvested this morning in San Francisco.',
                'is_active' => true,
            ],
            [
                'farmer_seller_id' => $juan->id,
                'category_id' => $vegetables->id,
                'name' => 'Tomato',
                'unit' => ListingUnit::Kilogram,
                'price_per_unit' => 65,
                'quantity_available' => 40,
                'description' => 'Firm salad tomatoes, unsprayed after fruit set.',
                'is_active' => true,
            ],
            [
                'farmer_seller_id' => $juan->id,
                'category_id' => $vegetables->id,
                'name' => 'Sitaw',
                'unit' => ListingUnit::Bundle,
                'price_per_unit' => 25,
                'quantity_available' => 30,
                'description' => 'Yard-long beans, bundled for household use.',
                'is_active' => true,
            ],
            [
                'farmer_seller_id' => $maria->id,
                'category_id' => $fruit->id,
                'name' => 'Carabao mango',
                'unit' => ListingUnit::Kilogram,
                'price_per_unit' => 120,
                'quantity_available' => 18,
                'description' => 'Sweet ripe mangoes from Tejero backyard trees.',
                'is_active' => true,
            ],
            [
                'farmer_seller_id' => $maria->id,
                'category_id' => $fruit->id,
                'name' => 'Lakatan banana',
                'unit' => ListingUnit::Kilogram,
                'price_per_unit' => 70,
                'quantity_available' => 22,
                'description' => 'Lakatan hands, ready to eat in 2–3 days.',
                'is_active' => true,
            ],
            [
                'farmer_seller_id' => $maria->id,
                'category_id' => $herbs->id,
                'name' => 'Tanglad',
                'unit' => ListingUnit::Bundle,
                'price_per_unit' => 15,
                'quantity_available' => 40,
                'description' => 'Lemongrass bundles for tea and cooking.',
                'is_active' => false,
            ],
            [
                'farmer_seller_id' => $pedro->id,
                'category_id' => $grains->id,
                'name' => 'Well-milled rice',
                'unit' => ListingUnit::Sack,
                'price_per_unit' => 1450,
                'quantity_available' => 8,
                'description' => 'Local palay milled in General Trias. One sack is 50 kg.',
                'is_active' => true,
            ],
            [
                'farmer_seller_id' => $pedro->id,
                'category_id' => $roots->id,
                'name' => 'Kamote',
                'unit' => ListingUnit::Kilogram,
                'price_per_unit' => 45,
                'quantity_available' => 50,
                'description' => 'Orange-flesh sweet potato from Navarro plots.',
                'is_active' => true,
            ],
        ];

        foreach ($listings as $listing) {
            Listing::query()->updateOrCreate(
                [
                    'farmer_seller_id' => $listing['farmer_seller_id'],
                    'name' => $listing['name'],
                ],
                $listing,
            );
        }
    }
}
