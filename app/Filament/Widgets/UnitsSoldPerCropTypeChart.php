<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

/**
 * Units are summed in each family's base unit. Grams and kilograms of the
 * same crop add up as kilograms. A tray stays a tray, on its own bar, labelled
 * with that base unit.
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
            'labels' => $rows->map(fn ($row): string => $row->crop.' ('.$row->unit_of_measure.')')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
