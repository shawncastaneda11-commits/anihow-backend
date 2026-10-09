<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Analytics\FarmerAnalyticsRequest;
use App\Http\Resources\Api\FarmerAnalyticsResource;
use App\Services\AnalyticsService;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsRange;

class FarmerAnalyticsController extends Controller
{
    public function __invoke(
        FarmerAnalyticsRequest $request,
        AnalyticsService $analytics,
        FarmerSalesAnalytics $sales,
        HarvestAnalytics $harvest,
    ): FarmerAnalyticsResource {
        if ($request->filled('range')) {
            return $this->forRange($request, $analytics, $sales, $harvest);
        }

        return $this->forPeriod($request, $analytics);
    }

    private function forPeriod(FarmerAnalyticsRequest $request, AnalyticsService $analytics): FarmerAnalyticsResource
    {
        $period = $request->validated('period', 'week');
        $since = $period === 'month'
            ? now()->startOfDay()->subDays(29)
            : now()->startOfDay()->subDays(6);
        $grouping = $period === 'month' ? 'week' : 'day';
        $user = $request->user();
        $units = $analytics->unitsSoldPerCropType($user, since: $since);

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
            'units_per_crop_type' => $units->map(fn (object $row): array => $this->unitRows($row))->all(),
            'best_selling' => $units->take(5)->map(fn (object $row): array => $this->unitRows($row))->values()->all(),
            'walk_in_share' => $analytics->walkInShare($user, since: $since),
        ]);
    }

    private function forRange(
        FarmerAnalyticsRequest $request,
        AnalyticsService $analytics,
        FarmerSalesAnalytics $sales,
        HarvestAnalytics $harvest,
    ): FarmerAnalyticsResource {
        $range = AnalyticsRange::fromRequest($request);
        $user = $request->user();
        $since = $range->from;
        $until = $range->to;
        $units = $analytics->unitsSoldPerCropType($user, since: $since, until: $until);

        return new FarmerAnalyticsResource([
            'period' => $range->key,
            'window_start' => $range->from->toDateString(),
            'window_end' => $range->to->toDateString(),
            'summary' => $analytics->completedSummary($user, since: $since, until: $until),
            'sales_per_period' => $analytics->salesPerPeriod($user, $range->grouping, since: $since, until: $until)
                ->map(fn (object $row): array => [
                    'period' => $row->period,
                    'orders' => (int) $row->orders,
                    'revenue' => (float) $row->revenue,
                ])
                ->all(),
            'units_per_crop_type' => $units->map(fn (object $row): array => $this->unitRows($row))->all(),
            'best_selling' => $units->take(5)->map(fn (object $row): array => $this->unitRows($row))->values()->all(),
            'walk_in_share' => $analytics->walkInShare($user, since: $since, until: $until),
            'range' => [
                'key' => $range->key,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
                'grouping' => $range->grouping,
                'category' => $range->category,
                'available_years' => $analytics->availableYears($user),
            ],
            'sales' => $sales->build($user, $range),
            'harvest' => $harvest->build($user, $range),
        ]);
    }

    /**
     * @return array{crop: mixed, unit: mixed, units: float, revenue: float}
     */
    private function unitRows(object $row): array
    {
        return [
            'crop' => $row->crop,
            'unit' => $row->unit_of_measure,
            'units' => (float) $row->units,
            'revenue' => (float) $row->revenue,
        ];
    }
}
