<?php

namespace Database\Factories;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\CropCareArticle;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CropCareArticle>
 */
class CropCareArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'farm_id' => Farm::factory(),
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'excerpt' => null,
            'body' => fake()->paragraphs(2, true),
            'category' => ArticleCategory::CropCare,
            'status' => ArticleStatus::Published,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function pestManagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => ArticleCategory::PestManagement,
        ]);
    }

    public function forFarm(Farm $farm, User $author): static
    {
        return $this->state(fn (array $attributes) => [
            'farm_id' => $farm->id,
            'created_by' => $author->id,
        ]);
    }
}
