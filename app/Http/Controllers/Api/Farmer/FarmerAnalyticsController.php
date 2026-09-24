<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Analytics\FarmerAnalyticsRequest;
use App\Http\Resources\Api\FarmerAnalyticsResource;
use App\Services\AnalyticsService;

class FarmerAnalyticsController extends Controller
{
    public function __invoke(FarmerAnalyticsRequest $request, AnalyticsService $analytics): FarmerAnalyticsResource
    {
        $period = $request->validated('period', 'week');
        $since = $period === 'month'
            ? now()->startOfDay()->subDays(29)
            : now()->startOfDay()->subDays(6);
        $grouping = $period === 'month' ? 'week' : 'day';
        $user = $request->user();

        return new FarmerAnalyticsResource([
            'period' => $period,
            'window_start' => $since->toDateString(),
            'window_end' => now()->toDateString(),
            'summary' => $analytics->completedSummary($user, since: $since),
            'sales_per_period' => $analytics->salesPerPeriod($user, $grouping, since: $since)
                ->map(fn (object $row): array => [
                    'period' => $row->period,
                    'orders' => (int) $row->orders,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'units_per_crop_type' => $analytics->unitsSoldPerCropType($user, since: $since)
                ->map(fn (object $row): array => [
                    'crop' => $row->crop,
                    'unit' => $row->unit_of_measure,
                    'units' => (float) $row->units,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'best_selling' => $analytics->bestSelling($user, $period, since: $since)
                ->map(fn (object $row): array => [
                    'crop' => $row->crop,
                    'unit' => $row->unit_of_measure,
                    'units' => (float) $row->units,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'walk_in_share' => $analytics->walkInShare($user, since: $since),
        ]);
    }
}
