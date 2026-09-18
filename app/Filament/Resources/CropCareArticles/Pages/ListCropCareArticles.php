<?php

namespace App\Filament\Resources\CropCareArticles\Pages;

use App\Filament\Resources\CropCareArticles\CropCareArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCropCareArticles extends ListRecords
{
    protected static string $resource = CropCareArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
