<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Support\ImageVariants;
use Database\Factories\CropCareArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Crop-care and pest-management reference content. Farm-scoped: each partner
 * farm's Content Editor writes their own guidance, tagged to entries in the
 * shared crop taxonomy. Read-only in the Android app.
 *
 * Reference content. Not a tracker, not a scheduler, not a decision engine.
 */
#[Fillable([
    'farm_id',
    'created_by',
    'title',
    'slug',
    'excerpt',
    'body',
    'image_path',
    'category',
    'status',
    'published_at',
])]
class CropCareArticle extends Model
{
    /** @use HasFactory<CropCareArticleFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => ArticleCategory::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // forceDeleted, not deleting: a soft-deleted article is still
        // recoverable and must keep its image.
        static::forceDeleted(function (CropCareArticle $article): void {
            app(ImageVariants::class)->delete($article->image_path);
        });
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Articles belong to one farm but tag to the shared taxonomy, and one
     * article may cover several crops.
     */
    public function cropTypes(): BelongsToMany
    {
        return $this->belongsToMany(CropType::class, 'article_crop_type');
    }

    public function imageUrl(): ?string
    {
        return app(ImageVariants::class)->url($this->image_path);
    }

    public function thumbnailUrl(): ?string
    {
        return app(ImageVariants::class)->thumbnailUrl($this->image_path);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    public function belongsToFarm(int $farmId): bool
    {
        return $this->farm_id === $farmId;
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }

    /**
     * @param  Builder<CropCareArticle>  $query
     * @return Builder<CropCareArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('crop_care_articles.status', ArticleStatus::Published);
    }

    /**
     * @param  Builder<CropCareArticle>  $query
     * @return Builder<CropCareArticle>
     */
    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('crop_care_articles.farm_id', $farmId);
    }

    /**
     * One-line list summary. The stored excerpt wins when the Content Editor
     * has written one; otherwise this derives from the body. Body is never
     * rewritten.
     */
    public function summary(int $limit = 90): string
    {
        if (filled($this->excerpt)) {
            return Str::limit($this->excerpt, $limit);
        }

        $firstLine = Str::of((string) $this->body)
            ->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '', '');

        $source = is_string($firstLine) && $firstLine !== ''
            ? $firstLine
            : trim(preg_replace('/\s+/u', ' ', (string) $this->body) ?? '');

        return Str::limit($source, $limit);
    }
}
