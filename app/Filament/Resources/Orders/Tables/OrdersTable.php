<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\FulfillmentPreference;
use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Placed => 'warning',
                        OrderStatus::Confirmed => 'info',
                        OrderStatus::Ready => 'primary',
                        OrderStatus::Completed => 'success',
                        OrderStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('total')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('tawad_total')
                    ->label('Tawad')
                    ->money('PHP')
                    ->toggleable(),
                TextColumn::make('amount_received')
                    ->label('Received')
                    ->money('PHP')
                    ->placeholder('Not yet')
                    // Cash is counted in person, so it can differ from total.
                    // The record says what changed hands, not what was owed.
                    ->color(fn (Order $record): ?string => $record->amount_received !== null
                        && (float) $record->amount_received !== (float) $record->total
                            ? 'warning'
                            : null)
                    ->toggleable(),
                TextColumn::make('fulfillment_preference')
                    ->label('Fulfillment')
                    ->formatStateUsing(fn (FulfillmentPreference $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::options()),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name'),
                Filter::make('placed_at')
                    ->label('Placed')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $placed, mixed $from): Builder => $placed->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $placed, mixed $until): Builder => $placed->whereDate('created_at', '<=', $until))),
                Filter::make('completed_at')
                    ->label('Completed')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $completed, mixed $from): Builder => $completed->whereDate('completed_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $completed, mixed $until): Builder => $completed->whereDate('completed_at', '<=', $until))),
                SelectFilter::make('fulfillment_preference')
                    ->options(FulfillmentPreference::options())
                    ->label('Fulfillment'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
