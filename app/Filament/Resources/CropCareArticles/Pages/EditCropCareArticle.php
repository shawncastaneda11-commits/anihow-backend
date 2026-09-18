<?php

namespace App\Filament\Resources\CropCareArticles\Pages;

use App\Filament\Resources\CropCareArticles\CropCareArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCropCareArticle extends EditRecord
{
    protected static string $resource = CropCareArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
