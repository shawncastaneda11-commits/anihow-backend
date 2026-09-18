<?php

namespace App\Actions\CropCare;

use App\Models\CropCareArticle;

class UpdateCropCareArticleAction
{
    /**
     * @param  array{title?: string, body?: string, category_id?: int}  $data
     */
    public function handle(CropCareArticle $article, array $data): CropCareArticle
    {
        $article->update($data);

        return $article->refresh()->load(['category', 'author']);
    }
}
