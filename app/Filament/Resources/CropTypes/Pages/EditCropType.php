<?php

namespace App\Filament\Resources\CropTypes\Pages;

use App\Filament\Resources\CropTypes\CropTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCropType extends EditRecord
{
    protected static string $resource = CropTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
