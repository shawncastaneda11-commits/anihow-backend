<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\TemporaryPasswordAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use App\Support\ActivityRecorder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<string> */
    private array $rolesBeforeSave = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetTemporaryPassword')
                ->label('Reset temporary password')
                ->requiresConfirmation()
                ->modalHeading('Reset temporary password')
                ->modalDescription('This signs the user out of the app and replaces their password. The new temporary password is shown once.')
                ->visible(function (): bool {
                    $actor = auth()->user();
                    $record = $this->getRecord();

                    if (! $actor instanceof User || ! $record instanceof User) {
                        return false;
                    }

                    return AccountAuthority::canManageOther($actor, $record);
                })
                ->action(function (): void {
                    $record = $this->getRecord();

                    if (! $record instanceof User) {
                        return;
                    }

                    $plain = $record->issueTemporaryPassword();

                    $this->replaceMountedAction('temporaryPassword', [
                        'password' => $plain,
                        'email' => $record->email,
                    ]);
                }),
            // For a lost or shared phone: ends every app and CMS session.
            Action::make('signOutEverywhere')
                ->label('Sign out everywhere')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This signs the person out of the app on every phone and out of the CMS. Their password does not change.')
                ->visible(fn (): bool => $this->getRecord() instanceof User
                    && AccountAuthority::canManageOther(auth()->user(), $this->getRecord()))
                ->action(function (): void {
                    $record = $this->getRecord();
                    abort_unless($record instanceof User && AccountAuthority::canManageOther(auth()->user(), $record), 403);

                    $record->signOutEverywhere();
                    ActivityRecorder::note('signed_out_everywhere', $record);

                    Notification::make()->title('Signed out everywhere.')->success()->send();
                }),
            // Deleting is rare and final, so it waits in a menu.
            ActionGroup::make([
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('delete', $this->record) ?? false),
            ])->tooltip('More'),
        ];
    }

    public function temporaryPasswordAction(): Action
    {
        return TemporaryPasswordAction::make();
    }

    /**
     * Changing your own password would otherwise end this session on the
     * next request, because the session stores the old password hash.
     */
    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof User) {
            $this->rolesBeforeSave = $record->getRoleNames()->values()->all();
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof User) {
            ActivityRecorder::roleChanged($record, $this->rolesBeforeSave, $record->load('roles')->getRoleNames()->values()->all());
        }

        if ($record->is(auth()->user()) && $record->wasChanged('password') && request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $record->getAuthPassword(),
            ]);
        }
    }

    /**
     * Role, farm, and status are account-management fields. A person editing
     * their own account can change their name and password, not these.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof User || ! AccountAuthority::canManageOther(auth()->user(), $record)) {
            unset($data['roles'], $data['farm_id'], $data['status']);
        }

        return $data;
    }
}
