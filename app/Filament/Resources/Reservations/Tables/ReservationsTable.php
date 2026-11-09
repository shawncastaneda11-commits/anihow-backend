<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\OrderPaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Support\Sales\SalesAccess;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    // A Content Editor sees the buyer's first name only.
                    ->formatStateUsing(fn (?string $state): ?string => SalesAccess::buyerName($state, auth()->user())),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller'),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->placeholder('—')
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
                TextColumn::make('listing_name')
                    ->label('Listing')
                    ->weight(FontWeight::SemiBold),
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
                    ->label('Reserved')
                    ->date('M j')
                    ->color('gray')
                    ->sortable(),
            ])
            // Clicking a row opens it read-only beside the list
            // (ListsSalesWithAPanel::openRecord).
            ->recordUrl(null)
            ->recordAction('openRecord')
            ->recordClasses(fn (Reservation $record, mixed $livewire): ?string => ($livewire->openId ?? null) === $record->getKey() ? 'anihow-row-on' : null)
            ->modifyQueryUsing(fn (Builder $query, mixed $livewire): Builder => $livewire instanceof ListReservations ? $livewire->applyListFilters($query) : $query)
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options(OrderPaymentStatus::options())
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name')
                    ->visible(fn (): bool => SalesAccess::seesAll(auth()->user())),
            ]);
    }
}
