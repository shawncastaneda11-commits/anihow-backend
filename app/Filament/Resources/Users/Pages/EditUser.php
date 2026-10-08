<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Users\Actions\TemporaryPasswordAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

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

                    return $actor->can(Permission::ManageAccounts->value)
                        && ! $record->isSuperAdmin()
                        && $actor->isNot($record);
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
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isSuperAdmin()),
        ];
    }

    public function temporaryPasswordAction(): Action
    {
        return TemporaryPasswordAction::make();
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
        if (! auth()->user()?->can(Permission::ManageAccounts->value)) {
            unset($data['roles'], $data['farm_id'], $data['status']);
        }

        return $data;
    }
}
