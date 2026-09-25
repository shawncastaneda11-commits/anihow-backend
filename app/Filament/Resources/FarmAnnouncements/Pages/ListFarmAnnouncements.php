<?php

namespace App\Filament\Resources\FarmAnnouncements\Pages;

use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFarmAnnouncements extends ListRecords
{
    protected static string $resource = FarmAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
