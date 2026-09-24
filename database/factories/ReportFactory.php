<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_id' => Listing::factory(),
            'reportable_type' => Listing::class,
            'reason' => fake()->randomElement(ReportReason::cases()),
            'details' => fake()->optional()->sentence(),
            'status' => ReportStatus::Open,
        ];
    }
}
