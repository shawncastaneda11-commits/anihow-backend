<?php

namespace App\Filament\Resources\CropCareArticles\Pages;

use App\Enums\ArticleStatus;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === ArticleStatus::Published->value) {
            $data['published_at'] ??= now();
        }

        // created_by is the original author and is never reassigned on edit.
        unset($data['created_by']);

        return $data;
    }
}
