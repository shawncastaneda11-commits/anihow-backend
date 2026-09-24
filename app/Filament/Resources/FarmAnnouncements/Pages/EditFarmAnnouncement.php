<?php

namespace App\Filament\Resources\FarmAnnouncements\Pages;

use App\Actions\Announcements\NotifyFarmAnnouncementRecipientsAction;
use App\Enums\Permission;
use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use App\Models\FarmAnnouncement;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFarmAnnouncement extends EditRecord
{
    protected static string $resource = FarmAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user === null || ! $user->can(Permission::ManageFarms->value)) {
            $data['farm_id'] = $user?->farm_id;
        }

        unset($data['author_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (! $record instanceof FarmAnnouncement) {
            return;
        }

        app(NotifyFarmAnnouncementRecipientsAction::class)->handle($record);
    }
}
