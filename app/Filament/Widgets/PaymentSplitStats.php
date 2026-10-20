<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Two cards that split the same completed sales two ways: how buyers paid
 * (cash or online) and where the sale was made (app or walk-in).
 */
class PaymentSplitStats extends Widget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.payment-split';

    /**
     * @return array{
     *     total: string,
     *     orders: string,
     *     cards: list<array{title: string, rows: list<array{label: string, amount: string, detail: string, percent: float, tone: string}>}>
     * }
     */
    protected function getViewData(): array
    {
        $sales = $this->sales();
        $total = (float) $sales['totals']['sales'];
        $payment = $sales['payment_split'];
        $source = $sales['source_split'];
        $walkIns = (int) $source['walk_in']['orders'];

        $cashDetail = $this->countText((int) $payment['cash']['orders'], 'order').' · '.$this->shareText((float) $payment['cash']['sales'], $total);

        if ($walkIns > 0) {
            $cashDetail .= ' · includes '.$this->countText($walkIns, 'walk-in sale');
        }

        return [
            'total' => $this->peso($total),
            'orders' => $this->countText((int) $sales['totals']['orders'], 'order'),
            'cards' => [
                [
                    'title' => 'How buyers paid',
                    'rows' => [
                        $this->row('Cash', (float) $payment['cash']['sales'], $total, $cashDetail, 'primary'),
                        $this->row(
                            'Online (GCash, Maya)',
                            (float) $payment['online']['sales'],
                            $total,
                            $this->countText((int) $payment['online']['orders'], 'order').' · '.$this->shareText((float) $payment['online']['sales'], $total),
                            'secondary',
                        ),
                    ],
                ],
                [
                    'title' => 'Where sales came from',
                    'rows' => [
                        $this->row(
                            'App orders',
                            (float) $source['app']['sales'],
                            $total,
                            $this->countText((int) $source['app']['orders'], 'order').' · '.$this->shareText((float) $source['app']['sales'], $total),
                            'primary',
                        ),
                        $this->row(
                            'Walk-in sales',
                            (float) $source['walk_in']['sales'],
                            $total,
                            $this->countText($walkIns, 'sale').' · '.$this->shareText((float) $source['walk_in']['sales'], $total),
                            'secondary',
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, amount: string, detail: string, percent: float, tone: string}
     */
    private function row(string $label, float $amount, float $total, string $detail, string $tone): array
    {
        return [
            'label' => $label,
            'amount' => $this->peso($amount),
            'detail' => $detail,
            'percent' => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
            'tone' => $tone,
        ];
    }
}
