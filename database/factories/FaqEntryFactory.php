<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\FaqEntry;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqEntry>
 */
class FaqEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => null,
            'intent_key' => fake()->unique()->slug(2),
            'roles' => [Role::FarmerSeller->value],
            'label' => fake()->sentence(4),
            'label_fil' => fake()->sentence(4),
            'keywords' => [fake()->unique()->word(), fake()->unique()->word()],
            'answer' => fake()->paragraph(),
            'answer_fil' => fake()->paragraph(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function forFarm(Farm $farm): static
    {
        return $this->state(fn (array $attributes): array => [
            'farm_id' => $farm->id,
        ]);
    }

    public function forIntent(string $intentKey): static
    {
        return $this->state(fn (array $attributes): array => [
            'intent_key' => $intentKey,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    public function forRoles(array $roles): static
    {
        return $this->state(fn (array $attributes): array => [
            'roles' => $roles,
        ]);
    }
}
