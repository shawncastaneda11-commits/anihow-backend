<?php

namespace App\Models;

use App\Support\ListingStorage;
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
        static::deleting(function (FarmPhoto $photo): void {
            if (filled($photo->path)) {
                ListingStorage::disk()->delete($photo->path);
            }
        });
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function url(): ?string
    {
        if (! filled($this->path)) {
            return null;
        }

        return ListingStorage::disk()->url($this->path);
    }
}
