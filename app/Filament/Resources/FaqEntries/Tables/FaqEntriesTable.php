<?php

namespace App\Filament\Resources\FaqEntries\Tables;

use App\Actions\Faq\ModerateFaqEntryAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\FaqEntry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FaqEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('intent_key')
                    ->label('Intent')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('farm.name')
                    ->label('Scope')
                    ->placeholder('System-wide')
                    ->sortable(),
                TextColumn::make('roles')
                    ->label('Roles')
                    ->formatStateUsing(function (mixed $state): string {
                        $roles = is_array($state) ? $state : [];

                        return collect($roles)
                            ->map(fn (string $role): string => Role::tryFrom($role)?->label() ?? $role)
                            ->implode(', ');
                    }),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('moderated_at')
                    ->label('Moderation')
                    ->badge()
                    ->state(fn (FaqEntry $record): ?string => $record->isModerated() ? 'Hidden by admin' : null)
                    ->color('danger'),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('farm_id')
                    ->label('Scope')
                    ->placeholder('All')
                    ->trueLabel('Farm overrides')
                    ->falseLabel('System-wide')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('farm_id'),
                        false: fn ($query) => $query->whereNull('farm_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (FaqEntry $record): bool => auth()->user()?->can('update', $record) ?? false),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FaqEntry $record): bool => ! $record->isModerated()
                        && (auth()->user()?->can('deactivate', $record) ?? false))
                    ->action(function (FaqEntry $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(ModerateFaqEntryAction::class)->deactivate($user, $record);
                    }),
                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FaqEntry $record): bool => $record->isModerated()
                        && (auth()->user()?->can('deactivate', $record) ?? false))
                    ->action(function (FaqEntry $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(ModerateFaqEntryAction::class)->reactivate($user, $record);
                    }),
                Action::make('moderateDelete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FaqEntry $record): bool => self::superAdminModeratesFarmRow($record))
                    ->action(function (FaqEntry $record): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(ModerateFaqEntryAction::class)->deleteFarmRow($user, $record);
                    }),
                DeleteAction::make()
                    ->visible(fn (FaqEntry $record): bool => (auth()->user()?->can('delete', $record) ?? false)
                        && ! self::superAdminModeratesFarmRow($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function superAdminModeratesFarmRow(FaqEntry $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can(Permission::ManageSystemFaq->value)
            && ! $record->isSystemWide()
            && $user->can('delete', $record);
    }
}
