<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

/**
 * Units are summed in each crop's own unit of measure, which is why the crop
 * type owns the unit rather than the listing. Two sellers listing kamatis in
 * different units would make this chart meaningless.
 */
class UnitsSoldPerCropTypeChart extends ChartWidget
{
    use ScopedAnalytics;

    protected ?string $heading = 'Units sold per crop type';

    protected static ?int $sort = 3;

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '365' => 'Last year',
        ];
    }

    protected function getData(): array
    {
        $rows = app(AnalyticsService::class)
            ->unitsSoldPerCropType(auth()->user(), (int) ($this->filter ?? 30));

        return [
            'datasets' => [
                [
                    'label' => 'Units sold',
                    'data' => $rows->pluck('units')->map(fn ($v): float => (float) $v)->all(),
                    'backgroundColor' => '#16a34a',
                ],
            ],
            'labels' => $rows->map(fn ($row): string => $row->crop.' ('.$row->unit.')')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
