<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use App\Filament\Resources\Users\Actions\SuspendAccountAction;
use App\Filament\Resources\Users\Pages\ListUsers;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (User $record): string => (string) $record->email)
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->state(fn (User $record): string => $record->primaryRole()?->label() ?? '—')
                    // A farmer-seller who has turned buying on.
                    ->description(fn (User $record): ?string => $record->alsoBuys() ? 'Also buys' : null),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record): string => match (true) {
                        ! $record->isActive() => 'Suspended',
                        (bool) $record->must_change_password => 'Must change password',
                        default => 'Active',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Suspended' => 'danger',
                        'Must change password' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M j')
                    ->color('gray')
                    ->sortable(),
            ])
            // Search, the role chips and the farm sit in one row above the
            // list (ListUsers), as in the design, so the table has no
            // search box or filter panel of its own.
            ->modifyQueryUsing(fn (Builder $query, mixed $livewire): Builder => $livewire instanceof ListUsers
                ? $livewire->applyListFilters($query)
                : $query)
            // Clicking an account opens its panel beside the list
            // (ListUsers::openAccount), and the open row stays marked.
            // No link to the full page on the row, or the link wins the click.
            ->recordUrl(null)
            ->recordAction('openAccount')
            ->recordClasses(fn (User $record, mixed $livewire): ?string => ($livewire->openId ?? null) === $record->getKey() ? 'anihow-row-on' : null)
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
