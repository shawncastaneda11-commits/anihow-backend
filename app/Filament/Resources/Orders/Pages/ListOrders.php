<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Exports\ExportOrderLedgerAction;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Filament\Concerns\ListsSalesWithAPanel;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Sales\SalesAccess;
use Closure;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The orders, with the chosen one open read-only beside the list. A Content
 * Editor sees their farm's, with the buyer's first name only.
 */
class ListOrders extends ListRecords
{
    use ListsSalesWithAPanel;

    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.sales.orders';

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

    public function intro(): string
    {
        return SalesAccess::seesAll(auth()->user())
            ? 'Every farm\'s orders. Sellers confirm and complete their own orders.'
            : 'Your farm\'s orders, to help sellers keep up. You can look, not change: sellers confirm and complete their own orders.';
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function applyListFilters(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $match): Builder => $match
            ->where('orders.order_number', 'like', "%{$term}%")
            ->orWhereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('name', 'like', "%{$term}%"))
            ->orWhereHas('items', fn (Builder $items): Builder => $items->where('listing_name', 'like', "%{$term}%")));
    }

    public function openedOrder(): ?Order
    {
        $record = $this->openedRecord();

        return $record instanceof Order ? $record : null;
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
            'ready' => ['Ready for pickup', fn (Builder $query): Builder => $query->whereIn('orders.status', [OrderStatus::Confirmed, OrderStatus::Ready])],
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
