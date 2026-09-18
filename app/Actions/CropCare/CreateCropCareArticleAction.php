<?php

namespace App\Actions\CropCare;

use App\Models\CropCareArticle;
use App\Models\User;

class CreateCropCareArticleAction
{
    /**
     * @param  array{title: string, body: string, category_id: int}  $data
     */
    public function handle(User $farmer, array $data): CropCareArticle
    {
        $article = $farmer->cropCareArticles()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'category_id' => $data['category_id'],
            'is_active' => true,
        ]);

        return $article->load(['category', 'author']);
    }
}
