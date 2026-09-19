<?php

namespace App\Filament\Resources\CropCareArticles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\CropCareArticles\CropCareArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCropCareArticle extends CreateRecord
{
    protected static string $resource = CropCareArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        // The farm field is hidden for a Content Editor, so it arrives empty
        // and is filled from the author. A Super Admin picks it in the form.
        $data['farm_id'] ??= $user->farm_id;
        $data['created_by'] = $user->id;

        if (($data['status'] ?? null) === ArticleStatus::Published->value) {
            $data['published_at'] ??= now();
        }

        return $data;
    }
}
