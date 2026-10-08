<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['email_verified_at'] = now();
        $data['password'] = Str::password(32);

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if (! $user instanceof User) {
            return;
        }

        $plain = $user->issueTemporaryPassword();

        Notification::make()
            ->title('Temporary password')
            ->body($user->temporaryPasswordNotice($plain))
            ->persistent()
            ->success()
            ->send();
    }
}
