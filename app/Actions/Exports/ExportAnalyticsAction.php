<?php

namespace App\Actions\Exports;

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\ExportLog;
use App\Models\User;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsCsvSections;
use App\Support\Analytics\AnalyticsRange;
use App\Support\CsvDownload;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every farm's analytics for the chosen dates, in the dashboard's sections
 * and with the dashboard's numbers.
 */
class ExportAnalyticsAction
{
    public function __construct(
        private FarmerSalesAnalytics $sales,
        private HarvestAnalytics $harvest,
        private CsvDownload $csv,
    ) {}

    public function download(User $actor, CarbonInterface $since, ?CarbonInterface $until = null): StreamedResponse
    {
        abort_unless($actor->can(Permission::GenerateExports->value), 403);

        $until ??= now();

        if ($since->greaterThan($until)) {
            [$since, $until] = [$until, $since];
        }

        $range = AnalyticsRange::fromValues('custom', $since->toDateString(), $until->toDateString(), null, 'all');
        $sales = $this->sales->build($actor, $range);
        $harvest = $this->harvest->build($actor, $range);
        $lines = $this->lines($range, $sales, $harvest);
        $filename = 'anihow-analytics-'.now()->toDateString().'.csv';

        ExportLog::query()->create([
            'user_id' => $actor->id,
            'type' => ExportType::Analytics,
            'filters' => [
                'since' => $since->toIso8601String(),
                'until' => $until->toIso8601String(),
            ],
            'row_count' => count(array_filter($lines, fn (array $line): bool => $line !== [])),
        ]);

        return $this->csv->stream($filename, function ($handle) use ($lines): void {
            foreach ($lines as $line) {
                fputcsv($handle, $this->csv->row($line));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $harvest
     * @return list<list<mixed>>
     */
    private function lines(AnalyticsRange $range, array $sales, array $harvest): array
    {
        return [
            ['AniHow analytics'],
            ['From', $range->from->format('M j, Y')],
            ['Until', $range->to->format('M j, Y')],
            ['Farm', 'All farms'],
            ['Category', 'All'],
            ['Generated at', now()->timezone('Asia/Manila')->format('M j, Y g:i A')],
            [],
            ...AnalyticsCsvSections::sales($sales),
            [],
            ['Sales over time'],
            [ucfirst($range->grouping), 'Orders', 'Sales'],
            ...$this->periods($range, $sales['per_period']),
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
     * @param  list<array{start: string, end: string, future: bool, orders: int, sales: float}>  $buckets
     * @return list<list<mixed>>
     */
    private function periods(AnalyticsRange $range, array $buckets): array
    {
        $rows = [];

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
        }

        return $rows;
    }
}
