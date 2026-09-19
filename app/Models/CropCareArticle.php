<?php

namespace App\Models;

use App\Support\ListingStorage;
use Database\Factories\CropCareArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['title', 'body', 'category_id', 'image_path', 'is_active'])]
class CropCareArticle extends Model
{
    /** Virtual grouping id for tips with no category_id. Not a categories row. */
    public const GENERAL_CATEGORY_ID = 0;

    /** @use HasFactory<CropCareArticleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CropCareArticle $article): void {
            if (filled($article->image_path)) {
                ListingStorage::disk()->delete($article->image_path);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        return ListingStorage::disk()->url($this->image_path);
    }

    public function isOfficial(): bool
    {
        return $this->created_by === null;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by !== null && $this->created_by === $user->id;
    }

    /**
     * @param  Builder<CropCareArticle>  $query
     * @return Builder<CropCareArticle>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('crop_care_articles.is_active', true);
    }

    /**
     * One-line list summary. Stored body is not rewritten.
     */
    public function excerpt(int $limit = 90): string
    {
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
