<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Reservations\CancelReservation;
use App\Enums\Permission;
use App\Enums\ReservationCancellationReason;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\InAppNotifier;
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
                        UserStatus::Pending => 'warning',
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
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->label('Role'),
                SelectFilter::make('status')
                    ->options(UserStatus::options()),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name'),
            ])
            ->recordActions([
                /*
                 * Farmer-seller registration requires Super Admin approval with
                 * a farm membership check. This is that approval.
                 */
                Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isPending()
                        && auth()->user()->can(Permission::ApproveFarmerSeller->value))
                    ->action(function (User $record): void {
                        $record->update([
                            'status' => UserStatus::Active,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);

                        app(InAppNotifier::class)->accountApproved($record);
                    }),
                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->isActive()
                        && ! $record->isSuperAdmin()
                        && auth()->user()->can(Permission::SuspendAccounts->value))
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
                    ->visible(fn (User $record): bool => ! $record->isSuperAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can(Permission::ManageAccounts->value)),
                ]),
            ]);
    }
}
