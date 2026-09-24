<?php

namespace App\Models;

use App\Support\ImageVariants;
use Database\Factories\FarmPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['farm_id', 'path', 'caption', 'sort_order'])]
class FarmPhoto extends Model
{
    /** @use HasFactory<FarmPhotoFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (FarmPhoto $photo): void {
            if ($photo->isDirty('path')) {
                $previous = $photo->getOriginal('path');
                app(ImageVariants::class)->delete(is_string($previous) ? $previous : null);
            }
        });

        static::deleting(function (FarmPhoto $photo): void {
            app(ImageVariants::class)->delete($photo->path);
        });
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function url(): ?string
    {
        return app(ImageVariants::class)->url($this->path);
    }

    public function thumbnailUrl(): ?string
    {
        return app(ImageVariants::class)->thumbnailUrl($this->path);
    }
}
