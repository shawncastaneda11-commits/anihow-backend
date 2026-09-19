<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Services\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Average discount given, plus the completed-order context it needs to be
 * read honestly. An average discount with no order count behind it says
 * nothing.
 */
class SalesOverviewWidget extends StatsOverviewWidget
{
    use ScopedAnalytics;

    protected ?string $heading = 'Last 30 days';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $analytics = app(AnalyticsService::class);
        $user = auth()->user();

        $discount = $analytics->averageDiscount($user, 30);
        $sales = $analytics->salesPerPeriod($user, 'day', 30);

        $revenue = (float) $sales->sum('revenue');
        $orders = (int) $sales->sum('orders');

        return [
            Stat::make('Completed orders', number_format($orders))
                ->description('Placed and cancelled orders are excluded')
                ->color('success'),

            Stat::make('Sales', 'PHP '.number_format($revenue, 2))
                ->description('Cash recorded at handover')
                ->color('primary'),

            Stat::make('Average tawad', 'PHP '.number_format($discount['average'], 2))
                ->description($discount['discounted_orders'].' of '.$discount['orders'].' orders had a tawad')
                ->color('warning'),
        ];
    }
}
