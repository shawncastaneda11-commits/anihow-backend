<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentSplitStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return $this->windowLabel();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $sales = $this->sales();

        $total = (float) $sales['totals']['sales'];
        $online = $sales['payment_split']['online'];
        $cash = $sales['payment_split']['cash'];
        $app = $sales['source_split']['app'];
        $walkIn = $sales['source_split']['walk_in'];

        return [
            Stat::make('Online', $this->peso((float) $online['sales']))
                ->description($online['orders'].' orders · '.$this->shareText((float) $online['sales'], $total)),
            Stat::make('Cash', $this->peso((float) $cash['sales']))
                ->description($cash['orders'].' orders, includes walk-in sales'),
            Stat::make('App orders', $this->peso((float) $app['sales']))
                ->description($app['orders'].' · '.$this->shareText((float) $app['sales'], $total)),
            Stat::make('Walk-in sales', $this->peso((float) $walkIn['sales']))
                ->description($walkIn['orders'].' · '.$this->shareText((float) $walkIn['sales'], $total)),
        ];
    }
}
