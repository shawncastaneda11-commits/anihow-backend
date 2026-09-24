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
        $days = $period === 'month' ? 30 : 7;
        $grouping = $period === 'month' ? 'week' : 'day';
        $periods = $period === 'month' ? 5 : 7;
        $user = $request->user();

        return new FarmerAnalyticsResource([
            'period' => $period,
            'summary' => $analytics->completedSummary($user, $days),
            'sales_per_period' => $analytics->salesPerPeriod($user, $grouping, $periods)
                ->map(fn (object $row): array => [
                    'period' => $row->period,
                    'orders' => (int) $row->orders,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'units_per_crop_type' => $analytics->unitsSoldPerCropType($user, $days)
                ->map(fn (object $row): array => [
                    'crop' => $row->crop,
                    'unit' => $row->unit_of_measure,
                    'units' => (float) $row->units,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'best_selling' => $analytics->bestSelling($user, $period)
                ->map(fn (object $row): array => [
                    'crop' => $row->crop,
                    'unit' => $row->unit_of_measure,
                    'units' => (float) $row->units,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'walk_in_share' => $analytics->walkInShare($user, $days),
        ]);
    }
}
