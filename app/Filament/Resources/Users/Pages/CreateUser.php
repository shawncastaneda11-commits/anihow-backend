<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\TemporaryPasswordAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Facades\FilamentView;
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

        $this->getCreatedNotification()?->send();

        $this->mountAction('temporaryPassword', [
            'password' => $plain,
            'email' => $user->email,
        ]);

        $this->halt();
    }

    public function temporaryPasswordAction(): Action
    {
        return TemporaryPasswordAction::make();
    }

    public function finishTemporaryPassword(): void
    {
        $redirectUrl = $this->getRedirectUrl();

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
    }
}
