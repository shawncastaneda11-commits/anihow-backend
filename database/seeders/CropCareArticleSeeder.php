<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CropCareArticle;
use Illuminate\Database\Seeder;

class CropCareArticleSeeder extends Seeder
{
    public function run(): void
    {
        $vegetables = Category::query()->where('slug', 'vegetables')->first();
        $fruit = Category::query()->where('slug', 'fruit')->first();

        $articles = [
            [
                'title' => 'Keeping tomato plants productive in Cavite heat',
                'category_id' => $vegetables?->id,
                'body' => 'Mulch the beds to hold moisture through General Trias dry spells, water at the base early in the morning, and pick ripe fruit often so plants keep setting. Watch for leaf spots after heavy rain and remove affected leaves instead of spraying on a calendar. Crop-cycle tracking is not part of AniHow; this note is general crop-care guidance only.',
                'is_active' => true,
            ],
            [
                'title' => 'Mango anthracnose after rainy weather',
                'category_id' => $fruit?->id,
                'body' => 'After prolonged rain, inspect mango fruit for dark sunken spots. Harvest mature fruit promptly, keep fallen fruit off the ground, and improve airflow by thinning crowded branches. Do not use this article as a spray schedule. AniHow does not track crop growth stages.',
                'is_active' => true,
            ],
            [
                'title' => 'Safe handling of harvested vegetables',
                'category_id' => null,
                'body' => 'Shade produce immediately after harvest, sort damaged pieces before listing them, and keep knives and crates clean. Cool, dry storage helps leafy vegetables last until a buyer pickup or walk-in sale.',
                'is_active' => true,
            ],
        ];

        foreach ($articles as $article) {
            CropCareArticle::query()->updateOrCreate(
                ['title' => $article['title']],
                $article,
            );
        }
    }
}
