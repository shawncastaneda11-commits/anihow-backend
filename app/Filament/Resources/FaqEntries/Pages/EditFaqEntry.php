<?php

namespace App\Filament\Resources\FaqEntries\Pages;

use App\Actions\Faq\ModerateFaqEntryAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\FaqEntries\FaqEntryResource;
use App\Models\FaqEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFaqEntry extends EditRecord
{
    protected static string $resource = FaqEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
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

        unset($data['moderated_at'], $data['moderated_by']);

        if ($user?->can(Permission::ManageSystemFaq->value)) {
            $data['farm_id'] = null;

            return $data;
        }

        $data['farm_id'] = $user?->farm_id;
        $data['roles'] = [Role::FarmerSeller->value];

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $user = auth()->user();

        if (! $record instanceof FaqEntry || ! $record->isModerated()) {
            return;
        }

        if ($user?->can(Permission::ManageSystemFaq->value)) {
            return;
        }

        app(ModerateFaqEntryAction::class)->notifySuperAdminsOfEditorRevision($record);
    }
}
