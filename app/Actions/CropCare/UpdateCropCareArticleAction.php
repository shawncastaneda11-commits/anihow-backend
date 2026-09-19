<?php

namespace App\Actions\CropCare;

use App\Models\CropCareArticle;
use Illuminate\Http\UploadedFile;

class UpdateCropCareArticleAction
{
    public function __construct(private SyncCropCareImage $images) {}

    /**
     * @param  array{title?: string, body?: string, category_id?: int}  $data
     */
    public function handle(CropCareArticle $article, array $data, ?UploadedFile $image = null): CropCareArticle
    {
        if ($image) {
            $data['image_path'] = $this->images->replace($article->image_path, $image);
        }

        $article->update($data);

        return $article->refresh()->load(['category', 'author']);
    }
}
