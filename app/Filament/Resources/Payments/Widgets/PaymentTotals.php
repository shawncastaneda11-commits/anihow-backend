<?php

namespace App\Filament\Resources\Payments\Widgets;

use App\Enums\PaymentProofStatus;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Totals above the payments list, over the payments this person may see:
 * every farm's for COSEL, their own farm's for a Content Editor.
 */
class PaymentTotals extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $month = now()->startOfMonth();
        $query = fn () => PaymentResource::getEloquentQuery()->withoutEagerLoads();

        $accepted = $query()->where('payment_proofs.status', PaymentProofStatus::Accepted)->where('payment_proofs.created_at', '>=', $month);
        $waiting = $query()->where('payment_proofs.status', PaymentProofStatus::Pending)->count();
        $rejected = $query()->where('payment_proofs.status', PaymentProofStatus::Rejected)->where('payment_proofs.created_at', '>=', $month)->count();

        return [
            Stat::make('Paid online this month', '₱'.number_format((float) (clone $accepted)->sum('payment_proofs.amount'), 2))
                ->description((clone $accepted)->count().' payments'),
            Stat::make('Waiting for a seller', (string) $waiting)
                ->description('Sellers confirm in the app')
                ->color($waiting > 0 ? 'warning' : 'gray'),
            Stat::make('Rejected this month', (string) $rejected)
                ->description('The buyer is told why'),
        ];
    }
}
