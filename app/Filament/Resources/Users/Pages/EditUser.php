<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isSuperAdmin()),
        ];
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
