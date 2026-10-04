<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\ReservationStatus;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('listing_name')
                    ->label('Listing')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantity'),
                TextColumn::make('unit')
                    ->formatStateUsing(fn ($state): string => $state?->value ?? ''),
                TextColumn::make('line_total')
                    ->label('Locked total')
                    ->money('PHP'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReservationStatus $state): string => $state->label()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
