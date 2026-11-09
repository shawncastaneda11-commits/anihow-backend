<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Enums\PaymentProofStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Widgets\PaymentTotals;
use App\Support\Sales\SalesAccess;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return SalesAccess::seesAll(auth()->user())
            ? 'Online payments for every farm\'s orders and reservations.'
            : 'Online payments for your farm\'s orders and reservations. Sellers check and confirm their own; you see where each one stands.';
    }

    protected function getHeaderWidgets(): array
    {
        return [PaymentTotals::class];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $status = fn (PaymentProofStatus $status): \Closure => fn (Builder $query): Builder => $query->where('payment_proofs.status', $status);

        return [
            'all' => Tab::make('All'),
            'waiting' => Tab::make('Waiting for the seller')
                ->modifyQueryUsing($status(PaymentProofStatus::Pending))
                ->badge(fn (): int => $status(PaymentProofStatus::Pending)(PaymentResource::getEloquentQuery()->withoutEagerLoads())->count()),
            'accepted' => Tab::make('Accepted')->modifyQueryUsing($status(PaymentProofStatus::Accepted)),
            'rejected' => Tab::make('Rejected')->modifyQueryUsing($status(PaymentProofStatus::Rejected)),
        ];
    }
}
