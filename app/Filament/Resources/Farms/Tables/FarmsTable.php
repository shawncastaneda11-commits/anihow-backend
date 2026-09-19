<?php

namespace App\Filament\Resources\Farms\Tables;

use App\Enums\Permission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FarmsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('municipality')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('pickup_point')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('farmer_sellers_count')
                    ->label('Sellers')
                    ->counts('farmerSellers'),
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
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can(Permission::ManageFarms->value)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can(Permission::ManageFarms->value)),
                ]),
            ]);
    }
}
