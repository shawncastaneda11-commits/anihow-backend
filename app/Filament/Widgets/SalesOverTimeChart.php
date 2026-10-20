<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Sales per day, week or month, with the best period drawn in full color
 * and a count of the periods that had no sales.
 */
class SalesOverTimeChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected ?string $heading = 'Sales over time';

    protected ?string $maxHeight = '440px';

    protected ?string $emptyStateHeading = 'No completed sales in this period.';

    private const BAR_COLOR = 'rgba(88, 166, 125, 0.6)';

    private const BEST_COLOR = '#58A67D';

    public function getDescription(): ?string
    {
        $grouping = $this->dashboardRange()->grouping;
        $sales = $this->sales();
        $parts = ['Per '.$grouping];
        $best = $this->bestIndex($sales['per_period']);

        if ($best !== null) {
            $bucket = $sales['per_period'][$best];
            $parts[] = 'Best '.$grouping.': '.$this->periodLabel($bucket, $grouping).' · '.$this->peso((float) $bucket['sales']);
        }

        $quiet = count(array_filter(
            $sales['per_period'],
            fn (array $bucket): bool => ! $bucket['future'] && (float) $bucket['sales'] <= 0,
        ));

        if ($best !== null && $quiet > 0) {
            $parts[] = $this->countText($quiet, $grouping).' with no sales';
        }

        return implode(' · ', $parts);
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
        $best = $this->bestIndex($sales['per_period']);
        $labels = [];
        $values = [];
        $colors = [];

        foreach ($sales['per_period'] as $index => $bucket) {
            $labels[] = $this->periodLabel($bucket, $grouping);
            $values[] = $bucket['future'] ? 0 : round((float) $bucket['sales'], 2);
            $colors[] = $index === $best ? self::BEST_COLOR : self::BAR_COLOR;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderRadius' => 3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                aspectRatio: 1.5,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => '₱' + context.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '₱' + value.toLocaleString('en-PH'),
                        },
                    },
                },
            }
        JS);
    }

    /**
     * @param  list<array{future: bool, sales: float}>  $buckets
     */
    private function bestIndex(array $buckets): ?int
    {
        $best = null;

        foreach ($buckets as $index => $bucket) {
            if ($bucket['future'] || (float) $bucket['sales'] <= 0) {
                continue;
            }

            if ($best === null || (float) $bucket['sales'] > (float) $buckets[$best]['sales']) {
                $best = $index;
            }
        }

        return $best;
    }
}
