<?php

namespace Database\Factories;

use App\Enums\ExportType;
use App\Models\ExportLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportLog>
 */
class ExportLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ExportType::OrderLedger,
            'filters' => [],
            'row_count' => fake()->numberBetween(0, 20),
        ];
    }
}
