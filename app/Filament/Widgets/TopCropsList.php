<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Support\Analytics\HarvestCropNotes;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * The five best-selling crops by sales, each with its share of total sales
 * and the quantity sold, then every other crop in one "Others" row.
 */
class TopCropsList extends Widget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected string $view = 'filament.widgets.top-crops';

    /**
     * @return array{
     *     total: string,
     *     rows: list<array{rank: int|null, crop: string, sales: string, share: string, percent: float, sold: string|null}>
     * }
     */
    protected function getViewData(): array
    {
        $sales = $this->sales();
        $total = (float) $sales['totals']['sales'];
        $sold = $this->soldByCrop($sales['top_crops']);
        $rows = [];

        foreach ($sales['pie']['slices'] as $index => $slice) {
            $amounts = $sold[(string) $slice['crop_type_id']] ?? [];

            $rows[] = $this->row(
                $index + 1,
                (string) $slice['crop'],
                (float) $slice['sales'],
                $total,
                $amounts === [] ? null : implode(', ', $amounts).' sold',
            );
        }

        $others = $sales['pie']['others'];

        if (is_array($others)) {
            $rows[] = $this->row(null, 'Others ('.$this->countText((int) $others['crops'], 'crop').')', (float) $others['sales'], $total, null);
        }

        return [
            'total' => $this->peso($total),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array{crop_type_id: int|null, unit: string, quantity: float}>  $topCrops
     * @return array<string, list<string>>
     */
    private function soldByCrop(array $topCrops): array
    {
        $sold = [];

        foreach ($topCrops as $row) {
            $sold[(string) $row['crop_type_id']][] = HarvestCropNotes::amount((float) $row['quantity'], (string) $row['unit']);
        }

        return $sold;
    }

    /**
     * @return array{rank: int|null, crop: string, sales: string, share: string, percent: float, sold: string|null}
     */
    private function row(?int $rank, string $crop, float $amount, float $total, ?string $sold): array
    {
        return [
            'rank' => $rank,
            'crop' => $crop,
            'sales' => $this->peso($amount),
            'share' => $this->shareText($amount, $total),
            'percent' => $total > 0 ? min(100.0, round($amount / $total * 100, 1)) : 0.0,
            'sold' => $sold,
        ];
    }
}
