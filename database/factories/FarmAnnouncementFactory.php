<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmAnnouncement>
 */
class FarmAnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'author_id' => User::factory(),
            'title' => fake()->unique()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => AnnouncementAudience::Members,
            'starts_at' => null,
            'ends_at' => null,
            'is_pinned' => false,
        ];
    }

    public function members(): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => AnnouncementAudience::Members,
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => AnnouncementAudience::Public,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subHour(),
        ]);
    }

    public function forFarm(Farm $farm, ?User $author = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'farm_id' => $farm->id,
            'author_id' => $author?->id,
        ]);
    }
}
