<?php

namespace Database\Factories;

use App\Enums\AccountDeletionStatus;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountDeletionRequest>
 */
class AccountDeletionRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => null,
            'status' => AccountDeletionStatus::Pending,
            'processed_by' => null,
            'processed_at' => null,
            'rejection_note' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountDeletionStatus::Completed,
            'processed_at' => now(),
        ]);
    }

    public function rejected(?string $note = 'Not enough information.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountDeletionStatus::Rejected,
            'rejection_note' => $note,
            'processed_at' => now(),
        ]);
    }
}
