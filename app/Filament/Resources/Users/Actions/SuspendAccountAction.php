<?php

namespace App\Filament\Resources\Users\Actions;

use App\Actions\Reservations\CancelReservation;
use App\Enums\ReservationCancellationReason;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;

/**
 * Suspend an account: it can't sign in until COSEL (or ICTD, for an
 * administrator) lifts it, and the seller's reservations are cancelled.
 * Used in the row menu and in the account panel.
 */
class SuspendAccountAction
{
    public static function make(string $name = 'suspend'): Action
    {
        return Action::make($name)
            ->label('Suspend')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "Suspend {$record->name}?")
            ->modalDescription('They can\'t use AniHow until you lift it. Their app sessions end now.')
            ->visible(fn (?User $record): bool => $record !== null
                && $record->isActive()
                && (auth()->user()?->can('suspend', $record) ?? false))
            ->schema(function (User $record): array {
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
                    ->label('Reason')
                    ->required()
                    ->maxLength(255);

                return $fields;
            })
            ->action(function (User $record, array $data): void {
                abort_unless(auth()->user()?->can('suspend', $record) ?? false, 403);

                $record->update([
                    'status' => UserStatus::Suspended,
                    'suspended_at' => now(),
                    'suspension_reason' => $data['suspension_reason'],
                ]);

                app(CancelReservation::class)->forSeller(
                    $record,
                    ReservationCancellationReason::ListingRemoved,
                );
            });
    }
}
