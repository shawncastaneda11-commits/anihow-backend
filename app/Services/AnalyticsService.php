<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
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
     * Sales per period. Grouping is by day, week or month.
     *
     * @return Collection<int, object>
     */
    public function salesPerPeriod(?User $viewer = null, string $grouping = 'day', int $periods = 30): Collection
    {
        $format = match ($grouping) {
            'month' => '%Y-%m',
            'week' => '%x-W%v',
            default => '%Y-%m-%d',
        };

        $since = match ($grouping) {
            'month' => now()->subMonths($periods),
            'week' => now()->subWeeks($periods),
            default => now()->subDays($periods),
        };

        return $this->orderQuery($viewer)
            ->where('completed_at', '>=', $since)
            ->groupBy('period')
            ->orderBy('period')
            ->get([
                DB::raw("date_format(orders.completed_at, '{$format}') as period"),
                DB::raw('count(*) as orders'),
                DB::raw('sum(orders.total) as revenue'),
            ]);
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
