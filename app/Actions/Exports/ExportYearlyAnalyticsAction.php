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
use App\Support\Analytics\AnalyticsWorkbook;
use App\Support\Spreadsheet\XlsxReport;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportYearlyAnalyticsAction
{
    public function __construct(
        private FarmerSalesAnalytics $sales,
        private HarvestAnalytics $harvest,
    ) {}

    public function download(User $actor, int $year, ?int $farmId, string $category): BinaryFileResponse
    {
        $farmId = $this->resolvedFarmId($actor, $farmId);
        $category = in_array($category, ['all', 'fresh', 'value_added'], true) ? $category : 'all';

        $range = AnalyticsRange::fromValues('yearly', null, null, $year, $category, true);
        $year = (int) $range->year;
        $sales = $this->sales->build($actor, $range, $farmId);
        $harvest = $this->harvest->build($actor, $range, $farmId);
        $filename = $farmId === null
            ? 'anihow-analytics-'.$year.'.xlsx'
            : 'anihow-analytics-'.$year.'-farm-'.$farmId.'.xlsx';
        $farm = $farmId === null ? 'All farms' : (Farm::query()->find($farmId)?->name ?? 'Farm '.$farmId);
        $product = match ($category) {
            'fresh' => 'Fresh',
            'value_added' => 'Value-added',
            default => 'All',
        };
        $best = $sales['year_total']['best_month'] ?? null;
        $bestMonth = is_array($best)
            ? [Carbon::createFromFormat('!Y-m', (string) $best['key'])->format('M'), (float) $best['sales']]
            : null;

        $export = AnalyticsWorkbook::download(
            $filename,
            'AniHow yearly analytics — '.$farm,
            'Year '.$year.'  ·  Category: '.$product.'  ·  Generated '.now()->timezone('Asia/Manila')->format('M j, Y g:i A'),
            $sales,
            $harvest,
            $this->months($actor, $year, $farmId, $category),
            $bestMonth,
        );

        ExportLog::query()->create([
            'user_id' => $actor->id,
            'type' => ExportType::AnalyticsYearly,
            'filters' => [
                'year' => $year,
                'farm_id' => $farmId,
                'category' => $category,
            ],
            'row_count' => $export['rows'],
        ]);

        return $export['response'];
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
     * One row per month. Months still to come stay blank.
     *
     * @return array{columns: list<array{0: string, 1: float, 2?: ?string}>, rows: list<list<mixed>>, total: list<mixed>}
     */
    private function months(User $actor, int $year, ?int $farmId, string $category): array
    {
        $timezone = (string) config('app.timezone');
        $rows = [];

        for ($month = 1; $month <= 12; $month++) {
            $label = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->format('M');
            $future = $year === (int) now()->year && $month > (int) now()->month;

            if ($future) {
                $rows[] = [$label, null, null, null, null, null, null];

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

        $total = ['Total'];

        foreach (range(1, 6) as $column) {
            $total[] = round(array_sum(array_map(fn (array $row): float => (float) ($row[$column] ?? 0), $rows)), 2);
        }

        $total[1] = (int) $total[1];

        return [
            'columns' => [
                ['Month', 10],
                ['Orders', 10, XlsxReport::COUNT],
                ['Sales', 15, XlsxReport::PESO],
                ['Cash sales', 14, XlsxReport::PESO],
                ['Online sales', 14, XlsxReport::PESO],
                ['App sales', 14, XlsxReport::PESO],
                ['Walk-in sales', 14, XlsxReport::PESO],
            ],
            'rows' => $rows,
            'total' => $total,
        ];
    }
}
