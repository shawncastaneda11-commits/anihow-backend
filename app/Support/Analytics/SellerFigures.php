<?php

namespace App\Support\Analytics;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A farm's figures one seller at a time, for its Content Editor only. Like
 * the rest of the analytics, only completed orders count, by the day they
 * were completed. No buyer is named.
 */
final class SellerFigures
{
    public const PERIODS = [
        '7' => 'Last 7 days',
        '30' => 'Last 30 days',
        'month' => 'This month',
        'year' => 'This year',
    ];

    /** Sales this far below the period before earn a "Needs a look". */
    private const DROP = 0.10;

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            '7' => [$now->subDays(7)->startOfDay(), $now],
            'month' => [$now->startOfMonth(), $now],
            'year' => [$now->startOfYear(), $now],
            default => [$now->subDays(30)->startOfDay(), $now],
        };
    }

    /**
     * One row per farmer-seller on the farm.
     *
     * @return Collection<int, array{seller: User, sales: float, orders: int, previous: float, trend: list<float>, top_crop: ?string, listings: int, watch: ?string, watch_level: ?string}>
     */
    public static function rows(int $farmId, string $period): Collection
    {
        [$from, $to] = self::window($period);
        $previousFrom = $from->sub($from->diffAsCarbonInterval($to));

        $sellers = User::query()
            ->where('farm_id', $farmId)
            ->role(Role::FarmerSeller->value)
            ->withCount(['listings' => fn (Builder $query): Builder => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $current = self::completed($farmId, $from, $to)
            ->selectRaw('farmer_seller_id, count(*) as orders, sum(total) as sales')
            ->groupBy('farmer_seller_id')
            ->get()
            ->keyBy('farmer_seller_id');

        $previous = self::completed($farmId, $previousFrom, $from)
            ->selectRaw('farmer_seller_id, sum(total) as sales')
            ->groupBy('farmer_seller_id')
            ->pluck('sales', 'farmer_seller_id');

        $completedAt = self::completed($farmId, $from, $to)->get(['farmer_seller_id', 'completed_at', 'total'])->groupBy('farmer_seller_id');

        $topCrops = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('crop_types', 'crop_types.id', '=', 'order_items.crop_type_id')
            ->where('orders.farm_id', $farmId)
            ->where('orders.status', OrderStatus::Completed)
            ->whereBetween('orders.completed_at', [$from, $to])
            ->selectRaw('orders.farmer_seller_id as seller_id, coalesce(crop_types.name, order_items.listing_name) as crop, sum(order_items.line_total) as sales')
            ->groupByRaw('orders.farmer_seller_id, coalesce(crop_types.name, order_items.listing_name)')
            ->get()
            ->groupBy('seller_id')
            ->map(fn (Collection $crops): ?string => $crops->sortByDesc(fn (object $crop): float => (float) $crop->sales)->first()?->crop);

        return $sellers->map(function (User $seller) use ($current, $previous, $completedAt, $topCrops, $from, $to): array {
            $sales = (float) ($current[$seller->id]->sales ?? 0);
            $before = (float) ($previous[$seller->id] ?? 0);
            [$watch, $level] = self::watch($seller, $sales, $before);

            return [
                'seller' => $seller,
                'sales' => $sales,
                'orders' => (int) ($current[$seller->id]->orders ?? 0),
                'previous' => $before,
                'trend' => self::buckets($completedAt[$seller->id] ?? collect(), $from, $to),
                'top_crop' => $topCrops[$seller->id] ?? null,
                'listings' => (int) $seller->listings_count,
                'watch' => $watch,
                'watch_level' => $level,
            ];
        });
    }

    /**
     * One seller's figures for the panel.
     *
     * @return array{sales: float, orders: int, previous: float, average: float, tawad: float, tawad_orders: int, weeks: list<array{label: string, sales: float}>, crops: list<array{crop: string, sales: float}>, app_share: ?int}
     */
    public static function detail(int $farmId, User $seller, string $period): array
    {
        [$from, $to] = self::window($period);
        $previousFrom = $from->sub($from->diffAsCarbonInterval($to));
        $orders = self::completed($farmId, $from, $to)->where('farmer_seller_id', $seller->id)->get(['id', 'total', 'tawad_total', 'source', 'completed_at']);
        $count = $orders->count();
        $sales = (float) $orders->sum('total');

        $crops = OrderItem::query()
            ->leftJoin('crop_types', 'crop_types.id', '=', 'order_items.crop_type_id')
            ->whereIn('order_items.order_id', $orders->pluck('id'))
            ->selectRaw('coalesce(crop_types.name, order_items.listing_name) as crop, sum(order_items.line_total) as sales')
            ->groupByRaw('coalesce(crop_types.name, order_items.listing_name)')
            ->orderByDesc('sales')
            ->limit(6)
            ->get()
            ->map(fn (object $row): array => ['crop' => (string) $row->crop, 'sales' => (float) $row->sales])
            ->values()
            ->all();

        $appOrders = $orders->filter(fn (Order $order): bool => $order->source === OrderSource::App)->count();
        $buckets = self::buckets($orders, $from, $to);

        return [
            'sales' => $sales,
            'orders' => $count,
            'previous' => (float) self::completed($farmId, $previousFrom, $from)->where('farmer_seller_id', $seller->id)->sum('total'),
            'average' => $count > 0 ? $sales / $count : 0.0,
            'tawad' => (float) $orders->sum('tawad_total'),
            'tawad_orders' => $orders->filter(fn (Order $order): bool => (float) $order->tawad_total > 0)->count(),
            'weeks' => collect($buckets)->values()->map(fn (float $value, int $index): array => [
                'label' => self::bucketLabel($from, $to, $index),
                'sales' => $value,
            ])->all(),
            'crops' => $crops,
            'app_share' => $count > 0 ? (int) round($appOrders / $count * 100) : null,
        ];
    }

    /**
     * @return Builder<Order>
     */
    private static function completed(int $farmId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->where('farm_id', $farmId)
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $to->addSecond());
    }

    /**
     * Sales in four equal parts of the period, oldest first.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<float>
     */
    private static function buckets(Collection $orders, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $span = max(1, $to->getTimestamp() - $from->getTimestamp());
        $buckets = [0.0, 0.0, 0.0, 0.0];

        foreach ($orders as $order) {
            if ($order->completed_at === null) {
                continue;
            }

            $index = min(3, (int) floor(($order->completed_at->getTimestamp() - $from->getTimestamp()) / $span * 4));
            $buckets[max(0, $index)] += (float) $order->total;
        }

        return $buckets;
    }

    private static function bucketLabel(CarbonImmutable $from, CarbonImmutable $to, int $index): string
    {
        $span = ($to->getTimestamp() - $from->getTimestamp()) / 4;
        $start = $from->addSeconds((int) ($span * $index));

        return $index === 3 ? 'Latest' : 'From '.$start->format('M j');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function watch(User $seller, float $sales, float $before): array
    {
        if (! $seller->isActive()) {
            return ['Suspended', 'no'];
        }

        if ($seller->must_change_password) {
            return ["Hasn't signed in yet", 'warn'];
        }

        if ($sales <= 0 && $before > 0) {
            return ['No sales this period', 'no'];
        }

        if ($before > 0 && $sales < $before * (1 - self::DROP)) {
            return ['Sales down '.(int) round((1 - $sales / $before) * 100).'%', 'warn'];
        }

        if ((int) $seller->listings_count === 0) {
            return ['No listings shown', 'warn'];
        }

        return [null, null];
    }
}
