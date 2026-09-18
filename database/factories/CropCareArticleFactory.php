<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CropCareArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'category_id' => Category::factory(),
            'is_active' => true,
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function uncategorized(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => null,
        ]);
    }

    public function authoredBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
