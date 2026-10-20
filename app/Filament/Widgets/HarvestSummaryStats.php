<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Support\Analytics\HarvestCropNotes;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Harvests recorded in the period: the value of their good stock at the
 * price when harvested, what has sold, what is still unsold, and profit
 * once sellers record a production cost.
 */
class HarvestSummaryStats extends Widget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.harvest-summary';

    /**
     * @return array{
     *     window: string,
     *     empty: bool,
     *     stockValue?: string,
     *     stockDetail?: string,
     *     earned?: string,
     *     earnedPercent?: float,
     *     earnedDetail?: string,
     *     unsold?: string,
     *     profit?: array{value: string, loss: bool, lines: list<string>}|null
     * }
     */
    protected function getViewData(): array
    {
        $harvest = $this->harvest();
        $window = $this->windowLabel();

        if ((int) $harvest['records'] === 0) {
            return ['window' => $window, 'empty' => true];
        }

        $potential = (float) $harvest['income']['potential'];
        $actual = (float) $harvest['income']['actual'];
        $records = (int) $harvest['records'];
        $crops = count($harvest['crops']);

        $stockDetail = $records.' '.($records === 1 ? 'harvest' : 'harvests').' of '.$crops.' '.($crops === 1 ? 'crop' : 'crops')
            .', at the price when harvested. '.$this->rejectedText($harvest['crops']);

        if ((int) $harvest['estimated_records'] > 0) {
            $stockDetail .= ' · '.$harvest['estimated_records'].' estimated';
        }

        return [
            'window' => $window,
            'empty' => false,
            'stockValue' => $this->peso($potential),
            'stockDetail' => $stockDetail,
            'earned' => $this->peso($actual),
            'earnedPercent' => $potential > 0 ? min(100.0, round($actual / $potential * 100, 1)) : 0.0,
            'earnedDetail' => $this->shareText($actual, $potential).' of the stock value sold',
            'unsold' => $this->peso(max(0.0, $potential - $actual)),
            'profit' => $this->profit($harvest),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $crops
     */
    private function rejectedText(array $crops): string
    {
        $byUnit = [];

        foreach ($crops as $crop) {
            $rejected = (float) $crop['rejected'];

            if ($rejected > 0) {
                $unit = (string) $crop['unit'];
                $byUnit[$unit] = ($byUnit[$unit] ?? 0.0) + $rejected;
            }
        }

        if ($byUnit === []) {
            return 'Nothing rejected.';
        }

        $parts = [];

        foreach ($byUnit as $unit => $quantity) {
            $parts[] = HarvestCropNotes::amount($quantity, $unit);
        }

        return implode(', ', $parts).' rejected.';
    }

    /**
     * @param  array<string, mixed>  $harvest
     * @return array{value: string, loss: bool, lines: list<string>}|null
     */
    private function profit(array $harvest): ?array
    {
        $cost = $harvest['cost'];

        if ((int) $cost['records_with_cost'] === 0) {
            return null;
        }

        $profitSoFar = (float) $cost['actual_profit'];

        return [
            'value' => $this->peso($profitSoFar),
            'loss' => $profitSoFar < 0,
            'lines' => [
                'Expected profit '.$this->peso((float) $cost['potential_profit']),
                'Cost '.$this->peso((float) $cost['cost_total']).'. Based on '.$cost['records_with_cost'].' of '.$harvest['records'].' harvests with a cost',
            ],
        ];
    }
}
