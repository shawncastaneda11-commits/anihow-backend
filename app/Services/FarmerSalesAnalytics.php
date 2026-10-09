<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Concerns\ScopesAnalytics;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Pricing\UnitConverter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Completed-order sales for one analytics window. Money is the sum of
 * order item line totals, which are already after tawad.
 */
class FarmerSalesAnalytics
{
    use ScopesAnalytics;

    /**
     * @return array{
     *     totals: array{sales: float, orders: int, average_order: float, tawad_total: float, average_tawad: float},
     *     pie: array{slices: list<array{crop_type_id: int|null, crop: string, sales: float, percent: float}>, others: array{crops: int, sales: float, percent: float}|null},
     *     top_crops: list<array{crop_type_id: int|null, crop: string, unit: string, quantity: float, sales: float}>,
     *     per_period: list<array{key: string, start: string, end: string, future: bool, orders: int, sales: float}>,
     *     payment_split: array{online: array{orders: int, sales: float}, cash: array{orders: int, sales: float}},
     *     source_split: array{app: array{orders: int, sales: float}, walk_in: array{orders: int, sales: float}},
     *     year_total: array{sales: float, orders: int, best_month: array{key: string, sales: float}|null}|null
     * }
     */
    public function build(?User $viewer, AnalyticsRange $range, ?int $farmId = null): array
    {
        $buckets = $range->buckets();
        $byKey = [];

        foreach ($buckets as $index => $bucket) {
            $byKey[$bucket['key']] = $index;
            $buckets[$index]['orders'] = 0;
            $buckets[$index]['sales'] = 0.0;
            $buckets[$index]['seen'] = [];
        }

        $baseUnit = UnitConverter::sqlBaseUnit('order_items.unit');
        $factor = UnitConverter::sqlFactor('order_items.unit');

        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('crop_types', 'crop_types.id', '=', 'order_items.crop_type_id')
            ->where('orders.status', OrderStatus::Completed)
            ->where('orders.completed_at', '>=', $range->from)
            ->where('orders.completed_at', '<=', $range->to);

        $this->scopeAnalytics($query, $viewer, 'orders');
        $this->scopeChosenFarm($query, $viewer, $farmId, 'orders');
        $this->applyCategory($query, $range);

        $rows = $query->select([
            'order_items.order_id',
            'order_items.crop_type_id',
            'crop_types.name as crop',
            'order_items.line_total',
            'order_items.tawad_amount',
            'orders.payment_method',
            'orders.source',
            'orders.completed_at',
        ])->selectRaw($baseUnit.' as base_unit')
            ->selectRaw('(order_items.quantity * ('.$factor.')) as base_quantity')
            ->get();

        $sales = 0.0;
        $tawad = 0.0;
        $orderIds = [];
        $byCrop = [];
        $byCropUnit = [];
        $payment = [
            'online' => ['orders' => 0, 'sales' => 0.0, 'seen' => []],
            'cash' => ['orders' => 0, 'sales' => 0.0, 'seen' => []],
        ];
        $source = [
            'app' => ['orders' => 0, 'sales' => 0.0, 'seen' => []],
            'walk_in' => ['orders' => 0, 'sales' => 0.0, 'seen' => []],
        ];

        foreach ($rows as $row) {
            $at = $row->completed_at instanceof CarbonInterface
                ? $row->completed_at
                : Carbon::parse((string) $row->completed_at);
            $key = $range->periodKey($at);
            $index = $byKey[$key] ?? null;

            if ($index === null || $buckets[$index]['future'] === true) {
                continue;
            }

            $line = (float) $row->line_total;
            $orderId = (int) $row->order_id;
            $cropId = $row->crop_type_id === null ? null : (int) $row->crop_type_id;
            $cropName = (string) $row->crop;
            $sales += $line;
            $tawad += (float) $row->tawad_amount;
            $orderIds[$orderId] = true;
            $buckets[$index]['sales'] += $line;

            if (! isset($buckets[$index]['seen'][$orderId])) {
                $buckets[$index]['seen'][$orderId] = true;
                $buckets[$index]['orders']++;
            }

            $cropKey = (string) $cropId;
            if (! isset($byCrop[$cropKey])) {
                $byCrop[$cropKey] = [
                    'crop_type_id' => $cropId,
                    'crop' => $cropName,
                    'sales' => 0.0,
                ];
            }
            $byCrop[$cropKey]['sales'] += $line;

            $unit = (string) $row->base_unit;
            $unitKey = $cropKey.'|'.$unit;
            if (! isset($byCropUnit[$unitKey])) {
                $byCropUnit[$unitKey] = [
                    'crop_type_id' => $cropId,
                    'crop' => $cropName,
                    'unit' => $unit,
                    'quantity' => 0.0,
                    'sales' => 0.0,
                ];
            }
            $byCropUnit[$unitKey]['quantity'] += (float) $row->base_quantity;
            $byCropUnit[$unitKey]['sales'] += $line;

            $payKey = (string) $row->payment_method === 'online_transfer' ? 'online' : 'cash';
            $payment[$payKey]['sales'] += $line;
            if (! isset($payment[$payKey]['seen'][$orderId])) {
                $payment[$payKey]['seen'][$orderId] = true;
                $payment[$payKey]['orders']++;
            }

            $sourceKey = (string) $row->source === 'walk_in' ? 'walk_in' : 'app';
            $source[$sourceKey]['sales'] += $line;
            if (! isset($source[$sourceKey]['seen'][$orderId])) {
                $source[$sourceKey]['seen'][$orderId] = true;
                $source[$sourceKey]['orders']++;
            }
        }

        $orderCount = count($orderIds);
        $sales = round($sales, 2);
        $tawad = round($tawad, 2);

        $perPeriod = array_map(fn (array $bucket): array => [
            'key' => $bucket['key'],
            'start' => $bucket['start'],
            'end' => $bucket['end'],
            'future' => $bucket['future'],
            'orders' => $bucket['orders'],
            'sales' => round($bucket['sales'], 2),
        ], $buckets);

        $totals = [
            'sales' => $sales,
            'orders' => $orderCount,
            'average_order' => $orderCount > 0 ? round($sales / $orderCount, 2) : 0.0,
            'tawad_total' => $tawad,
            'average_tawad' => $orderCount > 0 ? round($tawad / $orderCount, 2) : 0.0,
        ];

        return [
            'totals' => $totals,
            'pie' => $this->pie($byCrop, $sales),
            'top_crops' => $this->topCrops($byCropUnit),
            'per_period' => $perPeriod,
            'payment_split' => [
                'online' => ['orders' => $payment['online']['orders'], 'sales' => round($payment['online']['sales'], 2)],
                'cash' => ['orders' => $payment['cash']['orders'], 'sales' => round($payment['cash']['sales'], 2)],
            ],
            'source_split' => [
                'app' => ['orders' => $source['app']['orders'], 'sales' => round($source['app']['sales'], 2)],
                'walk_in' => ['orders' => $source['walk_in']['orders'], 'sales' => round($source['walk_in']['sales'], 2)],
            ],
            'year_total' => $this->yearTotal($range, $totals, $perPeriod),
        ];
    }

    private function applyCategory(Builder $query, AnalyticsRange $range): void
    {
        if ($range->category === 'value_added') {
            $query->where('crop_types.category', 'value_added');

            return;
        }

        if ($range->category === 'fresh') {
            $query->where('crop_types.category', '!=', 'value_added');
        }
    }

    /**
     * @param  array<string, array{crop_type_id: int|null, crop: string, sales: float}>  $byCrop
     * @return array{slices: list<array{crop_type_id: int|null, crop: string, sales: float, percent: float}>, others: array{crops: int, sales: float, percent: float}|null}
     */
    private function pie(array $byCrop, float $totalSales): array
    {
        $crops = array_values($byCrop);
        usort($crops, function (array $left, array $right): int {
            $bySales = $right['sales'] <=> $left['sales'];

            if ($bySales !== 0) {
                return $bySales;
            }

            return ($left['crop_type_id'] ?? 0) <=> ($right['crop_type_id'] ?? 0);
        });

        $sliceOf = function (array $crop) use ($totalSales): array {
            return [
                'crop_type_id' => $crop['crop_type_id'],
                'crop' => $crop['crop'],
                'sales' => round($crop['sales'], 2),
                'percent' => $this->percent($crop['sales'], $totalSales),
            ];
        };

        if (count($crops) <= 5) {
            return [
                'slices' => array_map($sliceOf, $crops),
                'others' => null,
            ];
        }

        $rest = array_slice($crops, 5);
        $othersSales = array_sum(array_column($rest, 'sales'));

        return [
            'slices' => array_map($sliceOf, array_slice($crops, 0, 5)),
            'others' => [
                'crops' => count($rest),
                'sales' => round($othersSales, 2),
                'percent' => $this->percent($othersSales, $totalSales),
            ],
        ];
    }

    /**
     * @param  array<string, array{crop_type_id: int|null, crop: string, unit: string, quantity: float, sales: float}>  $byCropUnit
     * @return list<array{crop_type_id: int|null, crop: string, unit: string, quantity: float, sales: float}>
     */
    private function topCrops(array $byCropUnit): array
    {
        $rows = array_values($byCropUnit);
        usort($rows, function (array $left, array $right): int {
            $bySales = $right['sales'] <=> $left['sales'];

            if ($bySales !== 0) {
                return $bySales;
            }

            return [$left['crop_type_id'] ?? 0, $left['unit']] <=> [$right['crop_type_id'] ?? 0, $right['unit']];
        });

        return array_map(fn (array $row): array => [
            'crop_type_id' => $row['crop_type_id'],
            'crop' => $row['crop'],
            'unit' => $row['unit'],
            'quantity' => round($row['quantity'], 2),
            'sales' => round($row['sales'], 2),
        ], $rows);
    }

    /**
     * @param  array{sales: float, orders: int, average_order: float, tawad_total: float, average_tawad: float}  $totals
     * @param  list<array{key: string, start: string, end: string, future: bool, orders: int, sales: float}>  $perPeriod
     * @return array{sales: float, orders: int, best_month: array{key: string, sales: float}|null}|null
     */
    private function yearTotal(AnalyticsRange $range, array $totals, array $perPeriod): ?array
    {
        if ($range->key !== 'yearly') {
            return null;
        }

        $best = null;

        foreach ($perPeriod as $row) {
            if ($row['future'] || $row['sales'] <= 0) {
                continue;
            }

            if ($best === null || $row['sales'] > $best['sales']) {
                $best = ['key' => $row['key'], 'sales' => $row['sales']];
            }
        }

        return [
            'sales' => $totals['sales'],
            'orders' => $totals['orders'],
            'best_month' => $best,
        ];
    }

    private function percent(float $part, float $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round($part / $whole * 100, 1);
    }
}
