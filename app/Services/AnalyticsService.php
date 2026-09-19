<?php

namespace App\Services;

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
    public function unitsSoldPerCropType(?User $viewer = null, ?int $days = null): Collection
    {
        return $this->itemQuery($viewer, $days)
            ->join('crop_types', 'crop_types.id', '=', 'order_items.crop_type_id')
            ->groupBy('crop_types.id', 'crop_types.name', 'crop_types.unit_of_measure')
            ->orderByDesc('units')
            ->get([
                'crop_types.name as crop',
                'crop_types.unit_of_measure as unit',
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
     * @return Collection<int, object>
     */
    public function salesPerPeriod(?User $viewer = null, string $grouping = 'day', int $periods = 30): Collection
    {
        $skeleton = $this->periodSkeleton($grouping, $periods);

        $since = match ($grouping) {
            'month' => now()->startOfMonth()->subMonths($periods - 1),
            'week' => now()->startOfWeek()->subWeeks($periods - 1),
            default => now()->startOfDay()->subDays($periods - 1),
        };

        $orders = $this->orderQuery($viewer)
            ->where('orders.completed_at', '>=', $since)
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
            ->map(fn (array $bucket, string $period): object => (object) [
                'period' => $period,
                'orders' => $bucket['orders'],
                'revenue' => round($bucket['revenue'], 2),
            ])
            ->values();
    }

    /**
     * Every period in the window, in order, starting at zero.
     *
     * @return Collection<string, array{orders: int, revenue: float}>
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
            $skeleton->put($this->periodKey($cursor, $grouping), ['orders' => 0, 'revenue' => 0.0]);

            $cursor = match ($grouping) {
                'month' => $cursor->copy()->addMonth(),
                'week' => $cursor->copy()->addWeek(),
                default => $cursor->copy()->addDay(),
            };
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
    public function bestSelling(?User $viewer = null, string $window = 'week', int $limit = 5): Collection
    {
        $days = $window === 'month' ? 30 : 7;

        return $this->unitsSoldPerCropType($viewer, $days)->take($limit);
    }

    /**
     * Average discount given: total tawad divided by the number of completed
     * orders. Feeds directly from the tawad amounts snapshotted on order items.
     *
     * @return array{average: float, total: float, orders: int, discounted_orders: int}
     */
    public function averageDiscount(?User $viewer = null, ?int $days = null): array
    {
        $query = $this->orderQuery($viewer);

        if ($days !== null) {
            $query->where('completed_at', '>=', now()->subDays($days));
        }

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
    private function itemQuery(?User $viewer, ?int $days): Builder
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed);

        if ($days !== null) {
            $query->where('orders.completed_at', '>=', now()->subDays($days));
        }

        return $this->scope($query, $viewer, 'orders');
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
