<?php

namespace App\Services;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Descriptive analytics. Sourced entirely from the system's own listings and
 * recorded orders: no external data, no projection of future values.
 *
 * It reports what happened. It does not forecast, classify price movement, or
 * recommend a price.
 *
 * Only completed orders count. A placed order is an intention, a cancelled one
 * is nothing, and counting either would overstate every figure on the
 * dashboard.
 *
 * Scope: a Super Admin sees system-wide, a farm's view is scoped to that farm,
 * a farmer-seller sees only their own.
 */
class AnalyticsService
{
    /**
     * Units sold per crop type, in each crop's own unit of measure.
     *
     * @return Collection<int, object>
     */
    public function unitsSoldPerCropType(?User $viewer = null, ?int $days = null, ?CarbonInterface $since = null, ?CarbonInterface $until = null): Collection
    {
        return $this->itemQuery($viewer, $days, $since, $until)
            ->join('crop_types', 'crop_types.id', '=', 'order_items.crop_type_id')
            ->groupBy('crop_types.id', 'crop_types.name', 'crop_types.unit_of_measure')
            ->orderByDesc('units')
            ->get([
                'crop_types.name as crop',
                // Aliased as unit_of_measure, not unit: OrderItem casts `unit` to
                // ListingUnit, and Eloquent applies the cast to the alias too.
                'crop_types.unit_of_measure as unit_of_measure',
                DB::raw('sum(order_items.quantity) as units'),
                DB::raw('sum(order_items.line_total) as revenue'),
            ]);
    }

    /**
     * Sales per period, grouped by day, week or month.
     *
     * Grouping happens in PHP rather than in SQL. date_format is MySQL-only
     * and strftime is SQLite-only, and this project runs on both: SQLite
     * locally, MySQL on the VPS. One implementation, both drivers.
     *
     * Periods with no completed orders are returned as zero rather than
     * omitted, so a quiet week shows as a dip instead of vanishing from the
     * chart.
     *
     * When $since is given, only orders on or after that instant count, even
     * if the first week bucket's calendar week started earlier. That first
     * bucket is labelled from $since.
     *
     * @return Collection<int, object>
     */
    public function salesPerPeriod(?User $viewer = null, string $grouping = 'day', int $periods = 30, ?CarbonInterface $since = null, ?CarbonInterface $until = null): Collection
    {
        $skeleton = $since !== null
            ? $this->periodSkeletonFromSince($grouping, $since, $until)
            : $this->periodSkeleton($grouping, $periods);

        $from = $since ?? match ($grouping) {
            'month' => now()->startOfMonth()->subMonths($periods - 1),
            'week' => now()->startOfWeek()->subWeeks($periods - 1),
            default => now()->startOfDay()->subDays($periods - 1),
        };

        $orders = $this->orderQuery($viewer)
            ->where('orders.completed_at', '>=', $from)
            ->when($until !== null, fn (Builder $query): Builder => $query->where('orders.completed_at', '<=', $until))
            ->get(['completed_at', 'total']);

        foreach ($orders as $order) {
            if ($order->completed_at === null) {
                continue;
            }

            $key = $this->periodKey($order->completed_at, $grouping);

            if (! $skeleton->has($key)) {
                continue;
            }

            $bucket = $skeleton->get($key);
            $bucket['orders']++;
            $bucket['revenue'] += (float) $order->total;
            $skeleton->put($key, $bucket);
        }

        return $skeleton
            ->map(fn (array $bucket): object => (object) [
                'period' => $bucket['label'],
                'orders' => $bucket['orders'],
                'revenue' => round($bucket['revenue'], 2),
            ])
            ->values();
    }

    /**
     * Every period in the window, in order, starting at zero.
     *
     * @return Collection<string, array{label: string, orders: int, revenue: float}>
     */
    private function periodSkeleton(string $grouping, int $periods): Collection
    {
        $cursor = match ($grouping) {
            'month' => now()->startOfMonth()->subMonths($periods - 1),
            'week' => now()->startOfWeek()->subWeeks($periods - 1),
            default => now()->startOfDay()->subDays($periods - 1),
        };

        $skeleton = collect();

        for ($i = 0; $i < $periods; $i++) {
            $key = $this->periodKey($cursor, $grouping);
            $skeleton->put($key, ['label' => $key, 'orders' => 0, 'revenue' => 0.0]);

            $cursor = match ($grouping) {
                'month' => $cursor->copy()->addMonth(),
                'week' => $cursor->copy()->addWeek(),
                default => $cursor->copy()->addDay(),
            };
        }

        return $skeleton;
    }

    /**
     * @return Collection<string, array{label: string, orders: int, revenue: float}>
     */
    private function periodSkeletonFromSince(string $grouping, CarbonInterface $since, ?CarbonInterface $until = null): Collection
    {
        $cursor = $since->copy()->startOfDay();
        $end = $until?->copy()->startOfDay() ?? now()->startOfDay();
        $skeleton = collect();
        $first = true;

        while ($cursor->lte($end)) {
            $key = $this->periodKey($cursor, $grouping);

            if (! $skeleton->has($key)) {
                $skeleton->put($key, [
                    'label' => $first ? $since->toDateString() : $key,
                    'orders' => 0,
                    'revenue' => 0.0,
                ]);
                $first = false;
            }

            $cursor = $cursor->copy()->addDay();
        }

        return $skeleton;
    }

    private function periodKey(CarbonInterface $at, string $grouping): string
    {
        return match ($grouping) {
            'month' => $at->format('Y-m'),
            'week' => $at->format('o-\\WW'),
            default => $at->format('Y-m-d'),
        };
    }

    /**
     * Best-selling produce, by units, within a window.
     *
     * @return Collection<int, object>
     */
    public function bestSelling(?User $viewer = null, string $window = 'week', int $limit = 5, ?CarbonInterface $since = null, ?CarbonInterface $until = null): Collection
    {
        $days = $since !== null ? null : ($window === 'month' ? 30 : 7);

        return $this->unitsSoldPerCropType($viewer, $days, $since, $until)->take($limit);
    }

    /**
     * Average discount given: total tawad divided by the number of completed
     * orders. Feeds directly from the tawad amounts snapshotted on order items.
     *
     * @return array{average: float, total: float, orders: int, discounted_orders: int}
     */
    public function averageDiscount(?User $viewer = null, ?int $days = null, ?CarbonInterface $since = null, ?CarbonInterface $until = null): array
    {
        $query = $this->applyCompletedSince($this->orderQuery($viewer), $days, $since, $until);

        $orders = (clone $query)->count();
        $total = (float) (clone $query)->sum('tawad_total');
        $discounted = (clone $query)->where('tawad_total', '>', 0)->count();

        return [
            'average' => $orders > 0 ? round($total / $orders, 2) : 0.0,
            'total' => round($total, 2),
            'orders' => $orders,
            'discounted_orders' => $discounted,
        ];
    }

    /**
     * Completed-order headline figures. Gross sales is the sum of order
     * totals, the same cash figure SalesOverviewWidget charts.
     *
     * @return array{completed_orders: int, units_sold: float, gross_sales: float, average_discount: float}
     */
    public function completedSummary(?User $viewer = null, ?int $days = null, ?CarbonInterface $since = null): array
    {
        $query = $this->applyCompletedSince($this->orderQuery($viewer), $days, $since);

        $orders = (clone $query)->count();
        $gross = (float) (clone $query)->sum('orders.total');
        $childDays = $since !== null ? null : $days;
        $units = (float) $this->unitsSoldPerCropType($viewer, $childDays, $since)->sum('units');

        return [
            'completed_orders' => $orders,
            'units_sold' => round($units, 2),
            'gross_sales' => round($gross, 2),
            'average_discount' => $this->averageDiscount($viewer, $childDays, $since)['average'],
        ];
    }

    /**
     * Walk-in vs app completed orders in the window.
     *
     * @return array{walk_in_orders: int, walk_in_sales: float, app_orders: int, app_sales: float}
     */
    public function walkInShare(?User $viewer = null, ?int $days = null, ?CarbonInterface $since = null): array
    {
        $query = $this->applyCompletedSince($this->orderQuery($viewer), $days, $since);

        $walkInOrders = 0;
        $walkInSales = 0.0;
        $appOrders = 0;
        $appSales = 0.0;

        foreach ((clone $query)->get(['source', 'total']) as $order) {
            $total = (float) $order->total;

            if ($order->source === OrderSource::WalkIn) {
                $walkInOrders++;
                $walkInSales += $total;
            } else {
                $appOrders++;
                $appSales += $total;
            }
        }

        return [
            'walk_in_orders' => $walkInOrders,
            'walk_in_sales' => round($walkInSales, 2),
            'app_orders' => $appOrders,
            'app_sales' => round($appSales, 2),
        ];
    }

    /**
     * Completed orders only, scoped to what the viewer may see.
     *
     * @return Builder<Order>
     */
    private function orderQuery(?User $viewer): Builder
    {
        $query = Order::query()->where('orders.status', OrderStatus::Completed);

        return $this->scope($query, $viewer, 'orders');
    }

    /**
     * @return Builder<OrderItem>
     */
    private function itemQuery(?User $viewer, ?int $days, ?CarbonInterface $since = null, ?CarbonInterface $until = null): Builder
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed);

        $this->applyCompletedSince($query, $days, $since, $until);

        return $this->scope($query, $viewer, 'orders');
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyCompletedSince(Builder $query, ?int $days, ?CarbonInterface $since, ?CarbonInterface $until = null): Builder
    {
        if ($since !== null) {
            $query->where('orders.completed_at', '>=', $since);
        } elseif ($days !== null) {
            $query->where('orders.completed_at', '>=', now()->subDays($days));
        }

        if ($until !== null) {
            $query->where('orders.completed_at', '<=', $until);
        }

        return $query;
    }

    /**
     * System-wide, farm-scoped, or own, decided by what the viewer holds.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scope(Builder $query, ?User $viewer, string $table): Builder
    {
        if ($viewer === null || $viewer->can(Permission::ViewSystemAnalytics->value)) {
            return $query;
        }

        if ($viewer->can(Permission::ViewFarmAnalytics->value) && $viewer->farm_id !== null) {
            return $query->where($table.'.farm_id', $viewer->farm_id);
        }

        return $query->where($table.'.farmer_seller_id', $viewer->id);
    }
}
