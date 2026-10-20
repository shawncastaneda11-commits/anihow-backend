<?php

namespace App\Actions\Exports;

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\ExportLog;
use App\Models\Farm;
use App\Models\User;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsCsvSections;
use App\Support\Analytics\AnalyticsRange;
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
            ? [Carbon::createFromFormat('!Y-m', (string) $best['key'])->format('M'), $best['sales']]
            : ['', ''];

        return [
            ['AniHow yearly analytics'],
            ['Year', $year],
            ['Farm', $farm],
            ['Category', $product],
            ['Generated at', now()->timezone('Asia/Manila')->format('M j, Y g:i A')],
            [],
            ...AnalyticsCsvSections::sales($sales, $bestMonth),
            [],
            ['Sales over time'],
            ['Month', 'Orders', 'Sales', 'Cash sales', 'Online sales', 'App sales', 'Walk-in sales'],
            ...$this->months($actor, $year, $farmId, $category),
            [],
            ...AnalyticsCsvSections::topCrops($sales),
            [],
            ...AnalyticsCsvSections::splits($sales),
            [],
            ...AnalyticsCsvSections::harvest($harvest),
            [],
            ...AnalyticsCsvSections::harvestByCrop($harvest),
        ];
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
                $built['payment_split']['cash']['sales'],
                $built['payment_split']['online']['sales'],
                $built['source_split']['app']['sales'],
                $built['source_split']['walk_in']['sales'],
            ];
        }

        return $rows;
    }
}
