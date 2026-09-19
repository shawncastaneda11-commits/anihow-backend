<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class SalesPerPeriodChart extends ChartWidget
{
    use ScopedAnalytics;

    protected ?string $heading = 'Sales per period';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'day';

    protected function getFilters(): ?array
    {
        return [
            'day' => 'Last 30 days',
            'week' => 'Last 12 weeks',
            'month' => 'Last 12 months',
        ];
    }

    protected function getData(): array
    {
        $grouping = $this->filter ?? 'day';
        $periods = $grouping === 'day' ? 30 : 12;

        $sales = app(AnalyticsService::class)
            ->salesPerPeriod(auth()->user(), $grouping, $periods);

        return [
            'datasets' => [
                [
                    'label' => 'Sales (PHP)',
                    'data' => $sales->pluck('revenue')->map(fn ($v): float => (float) $v)->all(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                ],
            ],
            'labels' => $sales->pluck('period')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
