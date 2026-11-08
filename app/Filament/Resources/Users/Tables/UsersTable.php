<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Reservations\CancelReservation;
use App\Enums\Permission;
use App\Enums\ReservationCancellationReason;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
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
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
            ->recordActions([
                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isActive()
                        && (auth()->user()?->can('suspend', $record) ?? false))
                    ->form(function (User $record): array {
                        $waiting = $record->unsettledPaymentOrderCount();
                        $fields = [];

                        if ($waiting > 0) {
                            $fields[] = Placeholder::make('open_payments')
                                ->label('Open payments')
                                ->content("This seller has {$waiting} orders waiting on a payment or a refund. Suspending does not change them.");
                            $fields[] = Checkbox::make('confirm_open_payments')
                                ->label('I understand these orders stay as they are')
                                ->accepted()
                                ->required();
                        }

                        $fields[] = TextInput::make('suspension_reason')
                            ->required()
                            ->maxLength(255);

                        return $fields;
                    })
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'status' => UserStatus::Suspended,
                            'suspended_at' => now(),
                            'suspension_reason' => $data['suspension_reason'],
                        ]);

                        app(CancelReservation::class)->forSeller(
                            $record,
                            ReservationCancellationReason::ListingRemoved,
                        );
                    }),
                ViewAction::make()
                    ->visible(fn (User $record): bool => ! (auth()->user()?->can('update', $record) ?? false)),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => auth()->user()?->can('delete', $record) ?? false),
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
