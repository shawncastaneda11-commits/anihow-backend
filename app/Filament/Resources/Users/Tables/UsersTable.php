<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Actions\SuspendAccountAction;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role as RoleModel;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (User $record): string => (string) $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->label() ?? $state),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->sortable()
                    ->placeholder('None'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => match ($state) {
                        UserStatus::Active => 'success',
                        UserStatus::Suspended => 'danger',
                    }),
                TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            // Clicking an account opens its panel beside the list
            // (ListUsers::openAccount), and the open row stays marked.
            ->recordAction('openAccount')
            ->recordClasses(fn (User $record, mixed $livewire): ?string => ($livewire->openId ?? null) === $record->getKey() ? 'anihow-row-on' : null)
            // Filters sit above the list, beside the role chips.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            // Applied as soon as one is picked, so no Apply button.
            ->deferFilters(false)
            ->filters([
                /*
                 * A Content Editor only ever sees their own farm's accounts,
                 * so role and farm filters would only lead to empty results.
                 */
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn (RoleModel $record): string => Role::tryFrom($record->name)?->label() ?? $record->name)
                    ->label('Role')
                    ->visible(fn (): bool => AccountAuthority::managesAny(auth()->user())),
                SelectFilter::make('status')
                    ->options(UserStatus::options()),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name')
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageAccounts->value) ?? false),
            ])
            // One "⋯" menu per row; clicking the row opens its panel.
            ->recordActions([
                ActionGroup::make([
                    SuspendAccountAction::make(),
                    ViewAction::make()
                        ->visible(fn (User $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn (User $record): bool => auth()->user()?->can('delete', $record) ?? false),
                ])->tooltip('More'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Checks each selected account like the single Delete
                    // button, so your own account and accounts the other
                    // office manages are skipped.
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => AccountAuthority::managesAny(auth()->user()))
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
