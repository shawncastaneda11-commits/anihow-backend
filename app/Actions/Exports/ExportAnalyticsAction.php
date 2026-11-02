<?php

namespace App\Actions\Exports;

use App\Actions\Exports\Concerns\ResolvesExportFarm;
use App\Enums\ExportType;
use App\Models\ExportLog;
use App\Models\Farm;
use App\Models\User;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Analytics\AnalyticsWorkbook;
use App\Support\Spreadsheet\XlsxReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The analytics for the chosen dates as an Excel report, in the dashboard's
 * sections and with the dashboard's numbers. The Super Admin gets every farm;
 * a Content Editor gets only their own farm.
 */
class ExportAnalyticsAction
{
    use ResolvesExportFarm;

    public function __construct(
        private FarmerSalesAnalytics $sales,
        private HarvestAnalytics $harvest,
    ) {}

    public function download(User $actor, CarbonInterface $since, ?CarbonInterface $until = null): BinaryFileResponse
    {
        $farmId = $this->resolvedFarmId($actor, null);
        $until ??= now();

        if ($since->greaterThan($until)) {
            [$since, $until] = [$until, $since];
        }

        $range = AnalyticsRange::fromValues('custom', $since->toDateString(), $until->toDateString(), null, 'all');
        $sales = $this->sales->build($actor, $range, $farmId);
        $harvest = $this->harvest->build($actor, $range, $farmId);
        $filename = $farmId === null
            ? 'anihow-analytics-'.now()->toDateString().'.xlsx'
            : 'anihow-analytics-'.now()->toDateString().'-farm-'.$farmId.'.xlsx';
        $farm = $farmId === null ? 'All farms' : (Farm::query()->find($farmId)?->name ?? 'Farm '.$farmId);

        $export = AnalyticsWorkbook::download(
            $filename,
            'AniHow analytics — '.$farm,
            $range->from->format('M j, Y').' – '.$range->to->format('M j, Y')
                .'  ·  Category: All  ·  Generated '.now()->timezone('Asia/Manila')->format('M j, Y g:i A'),
            $sales,
            $harvest,
            $this->overTime($range, $sales['per_period']),
        );

        ExportLog::query()->create([
            'user_id' => $actor->id,
            'type' => ExportType::Analytics,
            'filters' => [
                'since' => $since->toIso8601String(),
                'until' => $until->toIso8601String(),
                'farm_id' => $farmId,
            ],
            'row_count' => $export['rows'],
        ]);

        return $export['response'];
    }

    /**
     * @param  list<array{start: string, end: string, future: bool, orders: int, sales: float}>  $buckets
     * @return array{columns: list<array{0: string, 1: float, 2?: ?string}>, rows: list<list<mixed>>, total: list<mixed>}
     */
    private function overTime(AnalyticsRange $range, array $buckets): array
    {
        $rows = [];
        $orders = 0;
        $sales = 0.0;

        foreach ($buckets as $bucket) {
            if ($bucket['future']) {
                continue;
            }

            $start = Carbon::parse($bucket['start']);
            $end = Carbon::parse($bucket['end']);

            $rows[] = [
                match ($range->grouping) {
                    'week' => $start->format('M j').' – '.$end->format('M j, Y'),
                    'month' => $start->format('M Y'),
                    default => $start->format('M j, Y'),
                },
                $bucket['orders'],
                $bucket['sales'],
            ];
            $orders += (int) $bucket['orders'];
            $sales += (float) $bucket['sales'];
        }

        return [
            'columns' => [[ucfirst($range->grouping), 24], ['Orders', 10, XlsxReport::COUNT], ['Sales', 15, XlsxReport::PESO]],
            'rows' => $rows,
            'total' => ['Total', $orders, round($sales, 2)],
        ];
    }
}
