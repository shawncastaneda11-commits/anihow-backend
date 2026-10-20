<?php

namespace App\Support\Analytics;

/**
 * The CSV sections that mirror the dashboard, in its order: sales, top
 * crops, how buyers paid, where sales came from, harvest and harvest by
 * crop. Both analytics exports build their files from these.
 */
final class AnalyticsCsvSections
{
    /**
     * @param  array<string, mixed>  $sales
     * @param  list<mixed>|null  $bestMonth
     * @return list<list<mixed>>
     */
    public static function sales(array $sales, ?array $bestMonth = null): array
    {
        $totals = $sales['totals'];
        $lines = [
            ['Sales'],
            ['Total sales', $totals['sales']],
            ['Completed orders', $totals['orders']],
            ['App orders', $sales['source_split']['app']['orders']],
            ['Walk-in sales', $sales['source_split']['walk_in']['orders']],
            ['Average order', $totals['average_order']],
            ['Average tawad', $totals['average_tawad']],
            ['Tawad given in total', $totals['tawad_total']],
        ];

        if ($bestMonth !== null) {
            $lines[] = ['Best month', ...$bestMonth];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $sales
     * @return list<list<mixed>>
     */
    public static function topCrops(array $sales): array
    {
        $total = (float) $sales['totals']['sales'];
        $lines = [
            ['Top crops'],
            ['Rank', 'Crop', 'Unit', 'Quantity sold', 'Sales', 'Share of sales (%)'],
        ];

        foreach (array_values($sales['top_crops']) as $index => $crop) {
            $lines[] = [
                $index + 1,
                $crop['crop'],
                $crop['unit'],
                $crop['quantity'],
                $crop['sales'],
                self::percent((float) $crop['sales'], $total),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $sales
     * @return list<list<mixed>>
     */
    public static function splits(array $sales): array
    {
        $total = (float) $sales['totals']['sales'];
        $payment = $sales['payment_split'];
        $source = $sales['source_split'];

        return [
            ['How buyers paid'],
            ['Payment', 'Orders', 'Sales', 'Share of sales (%)'],
            self::splitRow('Cash', $payment['cash'], $total),
            self::splitRow('Online (GCash, Maya)', $payment['online'], $total),
            [],
            ['Where sales came from'],
            ['Source', 'Orders', 'Sales', 'Share of sales (%)'],
            self::splitRow('App orders', $source['app'], $total),
            self::splitRow('Walk-in sales', $source['walk_in'], $total),
        ];
    }

    /**
     * @param  array<string, mixed>  $harvest
     * @return list<list<mixed>>
     */
    public static function harvest(array $harvest): array
    {
        $potential = (float) $harvest['income']['potential'];
        $actual = (float) $harvest['income']['actual'];
        $cost = $harvest['cost'];
        $hasCost = (int) $cost['records_with_cost'] > 0;

        return [
            ['Harvest'],
            ['Harvests recorded', $harvest['records']],
            ['Estimated harvests', $harvest['estimated_records']],
            ['Crops harvested', count($harvest['crops'])],
            ['Value of harvested stock', $potential],
            ['Earned so far', $actual],
            ['Share of stock value sold (%)', self::percent($actual, $potential)],
            ['Unsold stock', round(max(0.0, $potential - $actual), 2)],
            ['Profit so far', $hasCost ? $cost['actual_profit'] : 'No cost recorded'],
            ['Expected profit', $hasCost ? $cost['potential_profit'] : 'No cost recorded'],
            ['Cost', $hasCost ? $cost['cost_total'] : 'No cost recorded'],
            ['Harvests with a cost', $cost['records_with_cost'].' of '.$harvest['records']],
        ];
    }

    /**
     * @param  array<string, mixed>  $harvest
     * @return list<list<mixed>>
     */
    public static function harvestByCrop(array $harvest): array
    {
        $lines = [
            ['Harvest by crop'],
            ['Crop', 'Unit', 'Harvested', 'Sold', 'In pending orders', 'Rejected', 'Removed', 'Left', 'Sold so far (%)', 'Stock value', 'Earned', 'Rejected by reason', 'Removed by reason'],
        ];

        $crops = $harvest['crops'];
        usort($crops, fn (array $left, array $right): int => (float) $right['potential_income'] <=> (float) $left['potential_income']);

        foreach ($crops as $crop) {
            $lines[] = [
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
                implode(', ', HarvestCropNotes::rejected($crop)),
                implode(', ', HarvestCropNotes::removed($crop)),
            ];
        }

        return $lines;
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
