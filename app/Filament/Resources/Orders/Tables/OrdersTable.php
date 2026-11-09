<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\FulfillmentPreference;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Sales\SalesAccess;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->weight(FontWeight::SemiBold)
                    ->sortable()
                    ->description(fn (Order $record): string => self::itemsSummary($record))
                    ->wrap(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->sortable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    // A Content Editor sees the buyer's first name only.
                    ->formatStateUsing(fn (?string $state): ?string => SalesAccess::buyerName($state, auth()->user()))
                    ->placeholder('Walk-in')
                    ->sortable(),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->sortable()
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
                TextColumn::make('total')
                    ->money('PHP')
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Placed => 'warning',
                        OrderStatus::Confirmed => 'info',
                        OrderStatus::Ready => 'primary',
                        OrderStatus::Completed => 'success',
                        OrderStatus::Cancelled => 'danger',
                    })
                    ->description(fn (Order $record): string => self::age($record)),
            ])
            // Clicking a row opens it read-only beside the list
            // (ListsSalesWithAPanel::openRecord).
            ->recordUrl(null)
            ->recordAction('openRecord')
            ->recordClasses(fn (Order $record, mixed $livewire): ?string => ($livewire->openId ?? null) === $record->getKey() ? 'anihow-row-on' : null)
            ->modifyQueryUsing(fn (Builder $query, mixed $livewire): Builder => $livewire instanceof ListOrders ? $livewire->applyListFilters($query) : $query)
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::options())
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name')
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
                Filter::make('placed_at')
                    ->label('Placed')
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user()))
                    ->schema([
                        DatePicker::make('from')->label('Placed from'),
                        DatePicker::make('until')->label('Placed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $placed, mixed $from): Builder => $placed->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $placed, mixed $until): Builder => $placed->whereDate('created_at', '<=', $until))),
                Filter::make('completed_at')
                    ->label('Completed')
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user()))
                    ->schema([
                        DatePicker::make('from')->label('Completed from'),
                        DatePicker::make('until')->label('Completed until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $completed, mixed $from): Builder => $completed->whereDate('completed_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $completed, mixed $until): Builder => $completed->whereDate('completed_at', '<=', $until))),
                SelectFilter::make('fulfillment_preference')
                    ->options(FulfillmentPreference::options())
                    ->label('Fulfillment')
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
            ])
            ->recordActions([
                // COSEL's full view, with the stall chat; the panel covers the rest.
                ViewAction::make()
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
            ])
            ->toolbarActions([]);
    }

    /**
     * "Sitaw × 5 bundle, Talong × 2 kg".
     */
    public static function itemsSummary(Order $record): string
    {
        return $record->items
            ->map(fn (OrderItem $item): string => $item->listing_name.' × '.self::number($item->quantity).' '.($item->unit?->value ?? ''))
            ->implode(', ');
    }

    /**
     * How long the order has been where it is: "Placed 26 hours ago",
     * "Ready since 2 hours ago", "Cancelled Nov 6 by the buyer".
     */
    public static function age(Order $record): string
    {
        return match ($record->status) {
            OrderStatus::Placed => 'Placed '.$record->created_at?->diffForHumans(),
            OrderStatus::Confirmed => 'Confirmed '.($record->confirmed_at ?? $record->created_at)?->diffForHumans(),
            OrderStatus::Ready => 'Ready since '.($record->ready_at ?? $record->created_at)?->diffForHumans(),
            OrderStatus::Completed => 'Completed '.($record->completed_at ?? $record->created_at)?->format('M j'),
            OrderStatus::Cancelled => trim('Cancelled '.$record->cancelled_at?->format('M j').($record->cancelled_by !== null ? ' by the '.strtolower($record->cancelled_by->label()) : '')),
        };
    }

    public static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    /** Placed over a day ago and still not confirmed by the seller. */
    public static function isLate(Order $record): bool
    {
        return $record->status === OrderStatus::Placed
            && $record->created_at !== null
            && $record->created_at->lt(now()->subDay());
    }
}
