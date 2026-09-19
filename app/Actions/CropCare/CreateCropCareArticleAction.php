<?php

namespace App\Actions\CropCare;

use App\Models\CropCareArticle;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class CreateCropCareArticleAction
{
    public function __construct(private SyncCropCareImage $images) {}

    /**
     * @param  array{title: string, body: string, category_id: int}  $data
     */
    public function handle(User $farmer, array $data, ?UploadedFile $image = null): CropCareArticle
    {
        $article = $farmer->cropCareArticles()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'category_id' => $data['category_id'],
            'is_active' => true,
            'image_path' => $image ? $this->images->store($image) : null,
        ]);

        return $article->load(['category', 'author']);
    }
}
