<?php

namespace App\Actions\Exports;

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\ExportLog;
use App\Models\Farm;
use App\Models\User;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Analytics\HarvestCropNotes;
use App\Support\CsvDownload;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportYearlyAnalyticsAction
{
    public function __construct(
        private FarmerSalesAnalytics $sales,
        private HarvestAnalytics $harvest,
        private CsvDownload $csv,
    ) {}

    public function download(User $actor, int $year, ?int $farmId, string $category): StreamedResponse
    {
        $farmId = $this->resolvedFarmId($actor, $farmId);
        $category = in_array($category, ['all', 'fresh', 'value_added'], true) ? $category : 'all';

        $range = AnalyticsRange::fromValues('yearly', null, null, $year, $category, true);
        $year = (int) $range->year;
        $sales = $this->sales->build($actor, $range, $farmId);
        $harvest = $this->harvest->build($actor, $range, $farmId);
        $lines = $this->lines($actor, $year, $farmId, $category, $sales, $harvest);
        $filename = $farmId === null
            ? 'anihow-analytics-'.$year.'.csv'
            : 'anihow-analytics-'.$year.'-farm-'.$farmId.'.csv';

        ExportLog::query()->create([
            'user_id' => $actor->id,
            'type' => ExportType::AnalyticsYearly,
            'filters' => [
                'year' => $year,
                'farm_id' => $farmId,
                'category' => $category,
            ],
            'row_count' => count(array_filter($lines, fn (array $line): bool => $line !== [])),
        ]);

        return $this->csv->stream($filename, function ($handle) use ($lines): void {
            foreach ($lines as $line) {
                fputcsv($handle, $this->csv->row($line));
            }
        });
    }

    private function resolvedFarmId(User $actor, ?int $farmId): ?int
    {
        if ($actor->can(Permission::GenerateExports->value)) {
            return $farmId;
        }

        if ($actor->can(Permission::ExportFarmAnalytics->value) && $actor->farm_id !== null) {
            if ($farmId !== null && (int) $farmId !== (int) $actor->farm_id) {
                abort(403);
            }

            return (int) $actor->farm_id;
        }

        abort(403);
    }

    /**
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $harvest
     * @return list<list<mixed>>
     */
    private function lines(User $actor, int $year, ?int $farmId, string $category, array $sales, array $harvest): array
    {
        $farm = $farmId === null ? 'All farms' : (Farm::query()->find($farmId)?->name ?? 'Farm '.$farmId);
        $product = match ($category) {
            'fresh' => 'Fresh',
            'value_added' => 'Value-added',
            default => 'All',
        };
        $best = $sales['year_total']['best_month'] ?? null;
        $bestMonth = is_array($best)
            ? Carbon::createFromFormat('!Y-m', (string) $best['key'])->format('M')
            : '';

        $lines = [
            ['AniHow yearly analytics'],
            ['Year', $year],
            ['Farm', $farm],
            ['Product type', $product],
            ['Generated at', now()->timezone('Asia/Manila')->format('M j, Y g:i A')],
            [],
            ['Summary'],
            ['Total sales', $sales['totals']['sales']],
            ['Completed orders', $sales['totals']['orders']],
            ['Average order', $sales['totals']['average_order']],
            ['Average tawad', $sales['totals']['average_tawad']],
            ['Best month', $bestMonth, is_array($best) ? $best['sales'] : ''],
            [],
            ['Monthly sales'],
            ['Month', 'Orders', 'Sales', 'Online sales', 'Cash sales', 'App sales', 'Walk-in sales'],
        ];

        foreach ($this->months($actor, $year, $farmId, $category) as $month) {
            $lines[] = $month;
        }

        $lines[] = [];
        $lines[] = ['Best sellers'];
        $lines[] = ['Crop', 'Sales', 'Percent'];

        foreach ($sales['pie']['slices'] as $slice) {
            $lines[] = [$slice['crop'], $slice['sales'], $slice['percent']];
        }

        if (is_array($sales['pie']['others'])) {
            $others = $sales['pie']['others'];
            $lines[] = ['Others ('.$others['crops'].' crops)', $others['sales'], $others['percent']];
        }

        $lines[] = [];
        $lines[] = ['Top crops'];
        $lines[] = ['Crop', 'Unit', 'Quantity', 'Sales'];

        foreach ($sales['top_crops'] as $crop) {
            $lines[] = [$crop['crop'], $crop['unit'], $crop['quantity'], $crop['sales']];
        }

        $lines[] = [];
        $lines[] = ['Harvest by crop'];
        $lines[] = ['Crop', 'Unit', 'Harvested', 'Rejected', 'Good', 'Sold', 'Waiting', 'Removed', 'Left', 'Expected income', 'Actual income', 'Notes'];

        foreach ($harvest['crops'] as $crop) {
            $lines[] = [
                $crop['crop'],
                $crop['unit'],
                $crop['harvested'],
                $crop['rejected'],
                $crop['good'],
                $crop['sold'],
                $crop['waiting'],
                $crop['removed'],
                $crop['remaining'],
                $crop['potential_income'],
                $crop['actual_income'],
                HarvestCropNotes::format($crop),
            ];
        }

        $cost = $harvest['cost'];
        $lines[] = [];
        $lines[] = ['Harvest totals'];
        $lines[] = ['Expected income', $harvest['income']['potential']];
        $lines[] = ['Actual income', $harvest['income']['actual']];
        $lines[] = ['Cost', (int) $cost['records_with_cost'] === 0 ? 'No cost recorded' : $cost['cost_total']];
        $lines[] = ['Expected profit', $cost['potential_profit'] ?? ''];
        $lines[] = ['Profit so far', $cost['actual_profit'] ?? ''];
        $lines[] = ['Harvests with cost', $cost['records_with_cost'].' of '.$harvest['records']];

        return $lines;
    }

    /**
     * @return list<list<mixed>>
     */
    private function months(User $actor, int $year, ?int $farmId, string $category): array
    {
        $timezone = (string) config('app.timezone');
        $rows = [];

        for ($month = 1; $month <= 12; $month++) {
            $label = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->format('M');
            $future = $year === (int) now()->year && $month > (int) now()->month;

            if ($future) {
                $rows[] = [$label, '', '', '', '', '', ''];

                continue;
            }

            $start = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->startOfDay();
            $built = $this->sales->build(
                $actor,
                AnalyticsRange::fromValues('custom', $start->toDateString(), $start->copy()->endOfMonth()->toDateString(), null, $category),
                $farmId,
            );

            $rows[] = [
                $label,
                $built['totals']['orders'],
                $built['totals']['sales'],
                $built['payment_split']['online']['sales'],
                $built['payment_split']['cash']['sales'],
                $built['source_split']['app']['sales'],
                $built['source_split']['walk_in']['sales'],
            ];
        }

        return $rows;
    }
}
