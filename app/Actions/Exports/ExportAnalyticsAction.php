<?php

namespace App\Actions\Exports;

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\ExportLog;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\CsvDownload;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAnalyticsAction
{
    public function __construct(
        private AnalyticsService $analytics,
        private CsvDownload $csv,
    ) {}

    public function download(User $actor, CarbonInterface $since, ?CarbonInterface $until = null): StreamedResponse
    {
        abort_unless($actor->can(Permission::GenerateExports->value), 403);

        $units = $this->analytics->unitsSoldPerCropType($actor, since: $since, until: $until);
        $sales = $this->analytics->salesPerPeriod($actor, 'day', since: $since, until: $until);
        $best = $this->analytics->bestSelling($actor, 'month', since: $since, until: $until);
        $discount = $this->analytics->averageDiscount($actor, since: $since, until: $until);

        $rowCount = $units->count() + $sales->count() + $best->count() + 1;
        $filename = 'anihow-analytics-'.now()->toDateString().'.csv';

        ExportLog::query()->create([
            'user_id' => $actor->id,
            'type' => ExportType::Analytics,
            'filters' => [
                'since' => $since->toIso8601String(),
                'until' => $until?->toIso8601String(),
            ],
            'row_count' => $rowCount,
        ]);

        return $this->csv->stream($filename, function ($handle) use ($units, $sales, $best, $discount): void {
            fputcsv($handle, ['Units sold per crop type']);
            fputcsv($handle, ['crop', 'unit', 'units', 'revenue']);
            foreach ($units as $row) {
                fputcsv($handle, [$row->crop, $row->unit_of_measure, $row->units, $row->revenue]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Sales per period']);
            fputcsv($handle, ['period', 'orders', 'revenue']);
            foreach ($sales as $row) {
                fputcsv($handle, [$row->period, $row->orders, $row->revenue]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Best-selling produce']);
            fputcsv($handle, ['crop', 'unit', 'units', 'revenue']);
            foreach ($best as $row) {
                fputcsv($handle, [$row->crop, $row->unit_of_measure, $row->units, $row->revenue]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Average discount']);
            fputcsv($handle, ['average', 'total', 'orders', 'discounted_orders']);
            fputcsv($handle, [
                $discount['average'],
                $discount['total'],
                $discount['orders'],
                $discount['discounted_orders'],
            ]);
        });
    }
}
