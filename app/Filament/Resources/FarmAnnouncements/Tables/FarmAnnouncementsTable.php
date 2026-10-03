<?php

namespace App\Filament\Resources\FarmAnnouncements\Tables;

use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use App\Models\FarmAnnouncement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FarmAnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageFarms->value) ?? false),
                TextColumn::make('audience')
                    ->badge()
                    ->formatStateUsing(fn (AnnouncementAudience $state): string => $state->label()),
                IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->boolean(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('audience')
                    ->options(AnnouncementAudience::options()),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name')
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageFarms->value) ?? false),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (FarmAnnouncement $record): bool => auth()->user()->can('update', $record)),
                DeleteAction::make()
                    ->visible(fn (FarmAnnouncement $record): bool => auth()->user()->can('delete', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
