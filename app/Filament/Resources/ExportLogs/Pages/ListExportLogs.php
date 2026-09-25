<?php

namespace App\Filament\Resources\ExportLogs\Pages;

use App\Filament\Resources\ExportLogs\ExportLogResource;
use Filament\Resources\Pages\ListRecords;

class ListExportLogs extends ListRecords
{
    protected static string $resource = ExportLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
