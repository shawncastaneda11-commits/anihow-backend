<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SalesSummaryStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return 'Sales';
    }

    protected function getDescription(): ?string
    {
        return 'Completed app orders and walk-in sales, after tawad. '.$this->windowLabel().'.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $sales = $this->sales();
        $source = $sales['source_split'];

        $total = Stat::make('Total sales', $this->peso((float) $sales['totals']['sales']))
            ->description('Money received in this period');

        if ($sales['totals']['orders'] === 0) {
            $total->description('No completed sales in this period.');
        }
        $best = $sales['year_total']['best_month'] ?? null;

        if ($this->dashboardRange()->key === 'yearly' && is_array($best)) {
            $month = Carbon::createFromFormat('!Y-m', (string) $best['key'])->format('M');
            $total->description('Best month: '.$month.' · '.$this->peso((float) $best['sales']));
        }

        return [
            $total,
            Stat::make('Completed orders', (string) $sales['totals']['orders'])
                ->description($this->countText((int) $source['app']['orders'], 'app order').', '.$this->countText((int) $source['walk_in']['orders'], 'walk-in sale')),
            Stat::make('Average order', $this->peso((float) $sales['totals']['average_order']))
                ->description('Total sales ÷ completed orders'),
            Stat::make('Average tawad', $this->peso((float) $sales['totals']['average_tawad']))
                ->description($this->peso((float) $sales['totals']['tawad_total']).' tawad given in total'),
        ];
    }
}
