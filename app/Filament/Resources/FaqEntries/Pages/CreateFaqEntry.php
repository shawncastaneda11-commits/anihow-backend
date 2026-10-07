<?php

namespace App\Filament\Resources\FaqEntries\Pages;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\FaqEntries\FaqEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaqEntry extends CreateRecord
{
    protected static string $resource = FaqEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user?->can(Permission::ManageSystemFaq->value)) {
            $data['farm_id'] = null;

            return $data;
        }

        $data['farm_id'] = $user?->farm_id;
        $data['roles'] = [Role::FarmerSeller->value];

        return $data;
    }
}
