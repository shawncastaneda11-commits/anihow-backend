<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\OrderPaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->placeholder('—'),
                TextColumn::make('listing_name')
                    ->label('Listing')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->formatStateUsing(fn (mixed $state, Reservation $record): string => trim(
                        rtrim(rtrim(number_format((float) $state, 2, '.', ''), '0'), '.').' '.($record->unit?->value ?? ''),
                    )),
                TextColumn::make('line_total')
                    ->label('Locked total')
                    ->money('PHP'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReservationStatus $state): string => $state->label()),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?OrderPaymentStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?OrderPaymentStatus $state): string => match ($state) {
                        OrderPaymentStatus::Paid, OrderPaymentStatus::Refunded => 'success',
                        OrderPaymentStatus::PaymentSent => 'info',
                        OrderPaymentStatus::RefundDue, OrderPaymentStatus::AwaitingPayment => 'warning',
                        OrderPaymentStatus::NotReceived => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ReservationStatus::cases())
                        ->mapWithKeys(fn (ReservationStatus $status): array => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options(OrderPaymentStatus::options()),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name'),
            ]);
    }
}
