<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InAppNotification>
 */
class InAppNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationType::OrderPlaced,
            'title' => 'New reservation',
            'body' => 'A buyer reserved produce.',
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}
