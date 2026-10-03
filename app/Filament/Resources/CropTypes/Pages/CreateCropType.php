<?php

namespace App\Filament\Resources\CropTypes\Pages;

use App\Enums\Permission;
use App\Filament\Resources\CropTypes\CropTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCropType extends CreateRecord
{
    protected static string $resource = CropTypeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user === null || ! $user->can(Permission::ManageCropTypes->value)) {
            $data['farm_id'] = $user?->farm_id;
        }

        $data['created_by'] = $user?->id;

        return $data;
    }
}
