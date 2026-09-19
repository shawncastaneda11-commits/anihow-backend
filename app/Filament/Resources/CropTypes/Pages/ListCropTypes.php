<?php

namespace App\Filament\Resources\CropTypes\Pages;

use App\Filament\Resources\CropTypes\CropTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCropTypes extends ListRecords
{
    protected static string $resource = CropTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
