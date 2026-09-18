<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Vegetables', 'description' => 'Leafy greens, fruiting vegetables, and garden produce.'],
            ['name' => 'Fruit', 'description' => 'Seasonal fruit grown in and around General Trias.'],
            ['name' => 'Grains', 'description' => 'Rice, corn, and other staple grains.'],
            ['name' => 'Root crops', 'description' => 'Kamote, cassava, gabi, and similar crops.'],
            ['name' => 'Herbs', 'description' => 'Culinary and household herbs.'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
