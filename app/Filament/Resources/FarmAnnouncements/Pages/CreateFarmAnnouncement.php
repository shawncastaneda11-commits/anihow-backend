<?php

namespace App\Filament\Resources\FarmAnnouncements\Pages;

use App\Actions\Announcements\NotifyFarmAnnouncementRecipientsAction;
use App\Enums\Permission;
use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use App\Models\FarmAnnouncement;
use Filament\Resources\Pages\CreateRecord;

class CreateFarmAnnouncement extends CreateRecord
{
    protected static string $resource = FarmAnnouncementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user === null || ! $user->can(Permission::ManageFarms->value)) {
            $data['farm_id'] = $user?->farm_id;
        }

        $data['author_id'] = $user?->id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof FarmAnnouncement) {
            return;
        }

        app(NotifyFarmAnnouncementRecipientsAction::class)->handle($record);
    }
}
