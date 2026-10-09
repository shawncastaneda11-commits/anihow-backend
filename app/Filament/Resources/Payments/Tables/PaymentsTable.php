<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProofStatus;
use App\Models\Farm;
use App\Models\PaymentProof;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reservation_id')
                    ->label('Type')
                    ->state(function (PaymentProof $record): string {
                        if ($record->order_id === null && $record->reservation_id !== null) {
                            $name = $record->reservation?->listing_name;

                            return $name !== null && $name !== '' ? "Reservation · {$name}" : 'Reservation';
                        }

                        return 'Order';
                    }),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('order.farm.name')
                    ->label('Farm')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('PHP'),
                TextColumn::make('paymentQr.wallet')
                    ->label('Wallet')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),
                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Proof')
                    ->badge()
                    ->formatStateUsing(fn (PaymentProofStatus $state): string => $state->label()),
                TextColumn::make('order.payment_status')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state): string => $state instanceof OrderPaymentStatus ? $state->label() : '—'),
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Payment status')
                    ->options(OrderPaymentStatus::options())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return $query->where(function (Builder $proofs) use ($value): void {
                            $proofs->whereHas(
                                'order',
                                fn (Builder $order): Builder => $order->where('payment_status', $value),
                            )->orWhereHas(
                                'reservation',
                                fn (Builder $reservation): Builder => $reservation->where('payment_status', $value),
                            );
                        });
                    }),
                SelectFilter::make('farm')
                    ->label('Farm')
                    ->options(fn (): array => Farm::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'order',
                            fn (Builder $order): Builder => $order->where('farm_id', $value),
                        );
                    }),
                Filter::make('sent')
                    ->label('Sent')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $sent, mixed $from): Builder => $sent->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $sent, mixed $until): Builder => $sent->whereDate('created_at', '<=', $until))),
            ])
            ->recordActions([
                Action::make('viewScreenshot')
                    ->label('View')
                    ->icon('heroicon-o-photo')
                    ->visible(fn (PaymentProof $record): bool => $record->hasScreenshot())
                    ->url(fn (PaymentProof $record): string => route('payments.proofs.screenshot', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
