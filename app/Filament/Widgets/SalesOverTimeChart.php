<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesOverTimeChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Sales over time';

    protected ?string $emptyStateHeading = 'No completed sales in this period.';

    public function getDescription(): ?string
    {
        return $this->windowLabel();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $sales = $this->sales();

        if ($sales['totals']['orders'] === 0) {
            return [];
        }

        $grouping = $this->dashboardRange()->grouping;
        $labels = [];
        $values = [];

        foreach ($sales['per_period'] as $bucket) {
            $labels[] = $this->periodLabel($bucket, $grouping);
            $values[] = $bucket['future'] ? 0 : round((float) $bucket['sales'], 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $values,
                    'backgroundColor' => '#58A67D',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
