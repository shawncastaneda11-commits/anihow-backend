<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HarvestSummaryStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return 'Harvest';
    }

    protected function getDescription(): ?string
    {
        return 'Harvests recorded in this period, and what those listings have sold so far. · '.$this->windowLabel();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $harvest = $this->harvest();

        if ($harvest['records'] === 0) {
            return [
                Stat::make('Earned so far', $this->peso(0.0))
                    ->description('No harvests recorded in this period.'),
                Stat::make('Expected income', $this->peso(0.0)),
                Stat::make('Harvests recorded', '0'),
            ];
        }

        $potential = (float) $harvest['income']['potential'];
        $actual = (float) $harvest['income']['actual'];
        $crops = count($harvest['crops']);
        $cropLabel = $crops.' '.($crops === 1 ? 'crop' : 'crops');

        if ((int) $harvest['estimated_records'] > 0) {
            $cropLabel .= ' · '.$harvest['estimated_records'].' estimated';
        }

        $stats = [
            Stat::make('Earned so far', $this->peso($actual))
                ->description($this->shareText($actual, $potential).' of '.$this->peso($potential).' expected'),
            Stat::make('Expected income', $this->peso($potential)),
            Stat::make('Harvests recorded', (string) $harvest['records'])
                ->description($cropLabel),
        ];

        $cost = $harvest['cost'];

        if ((int) $cost['records_with_cost'] === 0) {
            $stats[] = Stat::make('Profit — No cost recorded', '—');

            return $stats;
        }

        $expectedProfit = (float) $cost['potential_profit'];
        $profitSoFar = (float) $cost['actual_profit'];
        $profit = Stat::make('Profit so far', $this->peso($profitSoFar));

        if ($profitSoFar < 0) {
            $profit->color('danger');
        }

        $stats[] = Stat::make('Cost', $this->peso((float) $cost['cost_total']))
            ->description('Based on '.$cost['records_with_cost'].' of '.$harvest['records'].' harvests with a cost');
        $stats[] = Stat::make('Expected profit', $this->peso($expectedProfit));
        $stats[] = $profit;

        return $stats;
    }
}
