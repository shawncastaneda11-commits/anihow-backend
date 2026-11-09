<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Reservations\CancelReservation;
use App\Enums\Permission;
use App\Enums\ReservationCancellationReason;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use App\Support\ActivityRecorder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
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
            // Clicking an account opens its panel beside the list.
            ->recordAction('account')
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
                    self::accountPanel(),
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

    /**
     * The account panel: who this is, how they use AniHow, and the quick
     * actions. The full form (role, farm, temporary password) opens from it.
     */
    private static function accountPanel(): Action
    {
        return Action::make('account')
            ->label('Open')
            ->icon('heroicon-o-user-circle')
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::Large)
            ->modalHeading(fn (User $record): string => $record->name)
            ->modalDescription(fn (User $record): string => collect([
                Role::tryFrom((string) $record->getRoleNames()->first())?->label(),
                $record->created_at ? 'joined '.$record->created_at->format('M j, Y') : null,
            ])->filter()->implode(' · '))
            ->schema([
                Grid::make(2)->schema([
                    TextEntry::make('email')->state(fn (User $record): string => (string) $record->email),
                    TextEntry::make('phone')->label('Mobile number')->state(fn (User $record): string => $record->phone ?: '—'),
                    TextEntry::make('farm')->state(fn (User $record): string => $record->farm?->name ?? '—'),
                    TextEntry::make('status')
                        ->badge()
                        ->state(fn (User $record): string => $record->status->label())
                        ->color(fn (User $record): string => $record->isActive() ? 'success' : 'danger'),
                    TextEntry::make('app')
                        ->label('App')
                        ->state(fn (User $record): string => $record->last_app_version
                            ? 'Version '.$record->last_app_version.', '.$record->last_app_seen_at?->diffForHumans()
                            : 'Not seen on 1.0.3 or newer'),
                    TextEntry::make('password')
                        ->label('Password')
                        ->state(fn (User $record): string => $record->must_change_password ? 'Still on a temporary password' : 'Set by them'),
                ]),
                Text::make(fn (User $record): string => $record->isActive()
                    ? 'To suspend, change the role or farm, or set a temporary password, open the full account.'
                    : 'Suspended: '.($record->suspension_reason ?: 'no reason given').'. Open the full account to lift it.')
                    ->color('gray'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->extraModalFooterActions(fn (User $record, Action $action): array => array_values(array_filter([
                (auth()->user()?->can('update', $record) ?? false)
                    ? Action::make('openAccount')->label('Open full account')->url(UserResource::getUrl('edit', ['record' => $record]))
                    : Action::make('viewAccount')->label('Open full account')->color('gray')->url(UserResource::getUrl('view', ['record' => $record])),
                AccountAuthority::canManageOther(auth()->user(), $record)
                    ? $action->makeModalSubmitAction('signOutEverywhere', arguments: ['signOut' => true])
                        ->label('Sign out everywhere')
                        ->color('gray')
                    : null,
            ])))
            ->action(function (User $record, array $arguments): void {
                if (! ($arguments['signOut'] ?? false)) {
                    return;
                }

                abort_unless(AccountAuthority::canManageOther(auth()->user(), $record), 403);

                $record->signOutEverywhere();
                ActivityRecorder::note('signed_out_everywhere', $record);

                Notification::make()->title('Signed out everywhere.')->success()->send();
            });
    }
}
