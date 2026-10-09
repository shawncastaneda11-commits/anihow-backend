<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class BestSellersChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Best sellers';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'No completed sales in this period.';

    /**
     * @var list<string>
     */
    private const COLORS = ['#58A67D', '#E2A24A', '#7F77DD', '#378ADD', '#E8A87C'];

    public function getDescription(): ?string
    {
        return $this->windowLabel();
    }

    protected function getType(): string
    {
        return 'doughnut';
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

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($sales['pie']['slices'] as $index => $slice) {
            $labels[] = (string) $slice['crop'];
            $values[] = round((float) $slice['sales'], 2);
            $colors[] = self::COLORS[$index] ?? '#8C948F';
        }

        $others = $sales['pie']['others'];

        if (is_array($others)) {
            $labels[] = 'Others ('.$others['crops'].' crops)';
            $values[] = round((float) $others['sales'], 2);
            $colors[] = '#8C948F';
        }

        return [
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
