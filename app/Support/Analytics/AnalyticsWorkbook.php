<?php

namespace App\Support\Analytics;

use App\Support\Spreadsheet\XlsxReport as Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The analytics Excel file both exports share. It mirrors the dashboard: a
 * Summary tab with the key sales and harvest numbers, then one tab each for
 * sales over time, top crops, payments and sources, and harvest by crop.
 */
final class AnalyticsWorkbook
{
    /**
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $harvest
     * @param  array{columns: list<array{0: string, 1: float, 2?: ?string, 3?: bool}>, rows: list<list<mixed>>, total: list<mixed>}  $overTime
     * @param  array{0: string, 1: float|int}|null  $bestMonth  month name and its sales
     * @return array{response: BinaryFileResponse, rows: int}
     */
    public static function download(string $filename, string $title, string $context, array $sales, array $harvest, array $overTime, ?array $bestMonth = null): array
    {
        $path = tempnam(sys_get_temp_dir(), 'anihow-xlsx-');
        $report = new Xlsx($path);

        $report->summary(
            'Summary',
            $title,
            $context,
            ['Sales', self::salesSummary($sales, $bestMonth)],
            ['Harvest', self::harvestSummary($harvest)],
            'Each tab has one table: Sales over time · Top crops · Payments & sources · Harvest by crop',
        );
        $report->table('Sales over time', $overTime['columns'], $overTime['rows'], $overTime['total']);
        $report->table('Top crops', ...self::topCrops($sales));
        $report->tables('Payments & sources', ...self::splits($sales));
        $report->table('Harvest by crop', ...self::harvestByCrop($harvest));
        $report->close();

        $response = response()
            ->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();

        return ['response' => $response, 'rows' => $report->rowCount()];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @param  array{0: string, 1: float|int}|null  $bestMonth
     * @return list<array{0: string, 1: mixed, 2?: ?string}>
     */
    public static function salesSummary(array $sales, ?array $bestMonth = null): array
    {
        $totals = $sales['totals'];
        $lines = [
            ['Total sales', $totals['sales'], Xlsx::PESO],
            ['Completed orders', $totals['orders'], Xlsx::COUNT],
            ['App orders', $sales['source_split']['app']['orders'], Xlsx::COUNT],
            ['Walk-in sales', $sales['source_split']['walk_in']['orders'], Xlsx::COUNT],
            ['Average order', $totals['average_order'], Xlsx::PESO],
            ['Average tawad', $totals['average_tawad'], Xlsx::PESO],
            ['Tawad given in total', $totals['tawad_total'], Xlsx::PESO],
        ];

        if ($bestMonth !== null) {
            $lines[] = ['Best month', $bestMonth[0].' · ₱'.number_format((float) $bestMonth[1], 2)];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $harvest
     * @return list<array{0: string, 1: mixed, 2?: ?string}>
     */
    public static function harvestSummary(array $harvest): array
    {
        $potential = (float) $harvest['income']['potential'];
        $actual = (float) $harvest['income']['actual'];
        $cost = $harvest['cost'];
        $hasCost = (int) $cost['records_with_cost'] > 0;

        return [
            ['Harvests recorded', $harvest['records'], Xlsx::COUNT],
            ['Estimated harvests', $harvest['estimated_records'], Xlsx::COUNT],
            ['Crops harvested', count($harvest['crops']), Xlsx::COUNT],
            ['Value of harvested stock', $potential, Xlsx::PESO],
            ['Earned so far', $actual, Xlsx::PESO],
            ['Share of stock value sold', self::percent($actual, $potential), Xlsx::PERCENT],
            ['Unsold stock', round(max(0.0, $potential - $actual), 2), Xlsx::PESO],
            ['Profit so far', $hasCost ? $cost['actual_profit'] : 'No cost recorded', Xlsx::PESO],
            ['Expected profit', $hasCost ? $cost['potential_profit'] : 'No cost recorded', Xlsx::PESO],
            ['Cost', $hasCost ? $cost['cost_total'] : 'No cost recorded', Xlsx::PESO],
            ['Harvests with a cost', $cost['records_with_cost'].' of '.$harvest['records']],
        ];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @return array{0: list<array{0: string, 1: float, 2?: ?string, 3?: bool}>, 1: list<list<mixed>>, 2: list<mixed>}
     */
    public static function topCrops(array $sales): array
    {
        $total = (float) $sales['totals']['sales'];
        $rows = [];

        foreach (array_values($sales['top_crops']) as $index => $crop) {
            $rows[] = [
                $index + 1,
                $crop['crop'],
                $crop['unit'],
                $crop['quantity'],
                $crop['sales'],
                self::percent((float) $crop['sales'], $total),
            ];
        }

        return [
            [['Rank', 7, Xlsx::COUNT], ['Crop', 22], ['Unit', 10], ['Quantity sold', 13, Xlsx::QUANTITY], ['Sales', 14, Xlsx::PESO], ['Share of sales', 13, Xlsx::PERCENT]],
            $rows,
            [null, 'Total', null, null, $total, $rows === [] ? null : 100.0],
        ];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @return array{0: list<array{0: string, 1: float, 2?: ?string, 3?: bool}>, 1: list<array{0: string, 1: list<list<mixed>>}>}
     */
    public static function splits(array $sales): array
    {
        $total = (float) $sales['totals']['sales'];
        $payment = $sales['payment_split'];
        $source = $sales['source_split'];

        return [
            [['', 26], ['Orders', 10, Xlsx::COUNT], ['Sales', 14, Xlsx::PESO], ['Share of sales', 14, Xlsx::PERCENT]],
            [
                ['How buyers paid', [
                    self::splitRow('Cash', $payment['cash'], $total),
                    self::splitRow('Online (GCash, Maya)', $payment['online'], $total),
                ]],
                ['Where sales came from', [
                    self::splitRow('App orders', $source['app'], $total),
                    self::splitRow('Walk-in sales', $source['walk_in'], $total),
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $harvest
     * @return array{0: list<array{0: string, 1: float, 2?: ?string, 3?: bool}>, 1: list<list<mixed>>, 2: list<mixed>}
     */
    public static function harvestByCrop(array $harvest): array
    {
        $crops = $harvest['crops'];
        usort($crops, fn (array $left, array $right): int => (float) $right['potential_income'] <=> (float) $left['potential_income']);
        $rows = [];

        foreach ($crops as $crop) {
            $rows[] = [
                $crop['crop'],
                $crop['unit'],
                $crop['harvested'],
                $crop['sold'],
                $crop['waiting'],
                $crop['rejected'],
                $crop['removed'],
                $crop['remaining'],
                min(100.0, self::percent((float) $crop['sold'], (float) $crop['good'])),
                $crop['potential_income'],
                $crop['actual_income'],
                implode(', ', HarvestCropNotes::rejected($crop)) ?: '—',
                implode(', ', HarvestCropNotes::removed($crop)) ?: '—',
            ];
        }

        return [
            [
                ['Crop', 18],
                ['Unit', 9],
                ['Harvested', 11, Xlsx::QUANTITY],
                ['Sold', 9, Xlsx::QUANTITY],
                ['In pending orders', 10, Xlsx::QUANTITY],
                ['Rejected', 10, Xlsx::QUANTITY],
                ['Removed', 10, Xlsx::QUANTITY],
                ['Left', 9, Xlsx::QUANTITY],
                ['Sold so far', 11, Xlsx::PERCENT],
                ['Stock value', 14, Xlsx::PESO],
                ['Earned', 13, Xlsx::PESO],
                ['Rejected (why)', 28, null, true],
                ['Removed (why)', 34, null, true],
            ],
            $rows,
            ['Total', null, null, null, null, null, null, null, null, (float) $harvest['income']['potential'], (float) $harvest['income']['actual'], null, null],
        ];
    }

    /**
     * @param  array{orders: int, sales: float}  $split
     * @return list<mixed>
     */
    private static function splitRow(string $label, array $split, float $total): array
    {
        return [$label, $split['orders'], $split['sales'], self::percent((float) $split['sales'], $total)];
    }

    private static function percent(float $part, float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
