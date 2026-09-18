<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Enums\ListingUnit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('unit')
                    ->formatStateUsing(fn ($state): string => $state instanceof ListingUnit ? $state->value : (string) $state),
                TextColumn::make('price_per_unit')
                    ->label('Price')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('quantity_available')
                    ->label('Qty')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
