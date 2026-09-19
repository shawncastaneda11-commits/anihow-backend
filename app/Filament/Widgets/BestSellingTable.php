<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Services\AnalyticsService;
use Filament\Widgets\Widget;

/**
 * Best-selling produce, by week and by month side by side. A table rather than
 * a chart: the panel will ask for the numbers, not the shape.
 */
class BestSellingTable extends Widget
{
    use ScopedAnalytics;

    protected string $view = 'filament.widgets.best-selling-table';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $analytics = app(AnalyticsService::class);
        $user = auth()->user();

        return [
            'week' => $analytics->bestSelling($user, 'week'),
            'month' => $analytics->bestSelling($user, 'month'),
        ];
    }
}
