<?php

namespace App\Filament\Resources\CropTypes\Tables;

use App\Enums\ListingUnit;
use App\Models\CropType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CropTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label_fil')
                    ->label('Filipino')
                    ->searchable(),
                TextColumn::make('unit_of_measure')
                    ->label('Unit')
                    ->formatStateUsing(fn (ListingUnit $state): string => $state->label()),
                TextColumn::make('floor_price')
                    ->label('Floor')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('max_discount')
                    ->label('Max tawad')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('listings_count')
                    ->label('Listings')
                    ->counts('listings'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                /*
                 * Listings hold a restrictOnDelete foreign key, so a crop type
                 * in use cannot be deleted. Hiding the button is kinder than
                 * letting the database throw.
                 */
                DeleteAction::make()
                    ->visible(fn (CropType $record): bool => $record->listings()->doesntExist()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
