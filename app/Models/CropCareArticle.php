<?php

namespace App\Models;

use Database\Factories\CropCareArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'body', 'category_id', 'is_active'])]
class CropCareArticle extends Model
{
    /** @use HasFactory<CropCareArticleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<CropCareArticle>  $query
     * @return Builder<CropCareArticle>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('crop_care_articles.is_active', true);
    }
}
