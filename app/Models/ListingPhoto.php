<?php

namespace App\Models;

use App\Support\ListingStorage;
use Database\Factories\ListingPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'path', 'sort_order'])]
class ListingPhoto extends Model
{
    /** @use HasFactory<ListingPhotoFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (ListingPhoto $photo): void {
            if (filled($photo->path)) {
                ListingStorage::disk()->delete($photo->path);
            }
        });
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(): ?string
    {
        if (! filled($this->path)) {
            return null;
        }

        return ListingStorage::disk()->url($this->path);
    }
}
