<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->toggleable(),
                TextColumn::make('shop_name')
                    ->toggleable(),
                TextColumn::make('location')
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        return Role::tryFrom($state)?->label() ?? $state;
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->label('Role'),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record): bool => ! $record->isSuperAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
