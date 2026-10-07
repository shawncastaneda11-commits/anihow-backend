<?php

namespace App\Filament\Resources\CropTypes\Tables;

use App\Enums\Permission;
use App\Enums\ProductCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CropTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->placeholder('Shared')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageCropTypes->value) ?? false),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label_fil')
                    ->label('Filipino')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (ProductCategory $state): string => $state->label()),
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
                SelectFilter::make('farm_id')
                    ->label('Farm')
                    ->relationship('farm', 'name')
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageCropTypes->value) ?? false),
                SelectFilter::make('category')
                    ->options(ProductCategory::options()),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->emptyStateHeading('No crop types yet')
            ->emptyStateDescription('Crop types you add here belong to one farm. Another farm does not see them.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('This removes the crop type and its listings. Past orders keep their item names and prices.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('This removes the selected crop types and their listings. Past orders keep their item names and prices.'),
                ]),
            ]);
    }
}
