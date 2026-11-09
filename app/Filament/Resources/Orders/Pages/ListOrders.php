<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Exports\ExportOrderLedgerAction;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\Sales\SalesAccess;
use Closure;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->visible(fn (): bool => auth()->user()?->can(Permission::GenerateExports->value) ?? false)
                ->action(function (ExportOrderLedgerAction $export) {
                    return $export->download(
                        auth()->user(),
                        $this->getFilteredTableQuery(),
                        $this->tableFilters ?? [],
                    );
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        return SalesAccess::seesAll(auth()->user())
            ? null
            : 'Your farm\'s orders, to help sellers keep up. You can look, not change: sellers confirm and complete their own orders.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => ['All', fn (Builder $query): Builder => $query],
            'late' => ['Needs a look', fn (Builder $query): Builder => $query
                ->where('orders.status', OrderStatus::Placed)
                ->where('orders.created_at', '<', now()->subDay())],
            'placed' => ['Waiting to confirm', fn (Builder $query): Builder => $query->where('orders.status', OrderStatus::Placed)],
            'ready' => ['Confirmed or ready', fn (Builder $query): Builder => $query->whereIn('orders.status', [OrderStatus::Confirmed, OrderStatus::Ready])],
            'completed' => ['Completed', fn (Builder $query): Builder => $query->where('orders.status', OrderStatus::Completed)],
            'cancelled' => ['Cancelled', fn (Builder $query): Builder => $query->where('orders.status', OrderStatus::Cancelled)],
        ];

        return collect($tabs)
            ->map(fn (array $tab, string $key): Tab => Tab::make($tab[0])
                ->modifyQueryUsing($tab[1])
                ->badge(in_array($key, ['late', 'placed'], true) ? fn (): int => $this->countFor($tab[1]) : null))
            ->all();
    }

    /**
     * @param  Closure(Builder): Builder  $filter
     */
    private function countFor(Closure $filter): int
    {
        return $filter(OrderResource::getEloquentQuery()->withoutEagerLoads())->count();
    }
}
