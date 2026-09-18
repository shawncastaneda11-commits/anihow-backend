<?php

namespace App\Models;

use App\Enums\ListingUnit;
use App\Support\ListingStorage;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'farmer_seller_id',
    'category_id',
    'name',
    'unit',
    'price_per_unit',
    'quantity_available',
    'description',
    'image_path',
    'is_active',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'price_per_unit' => 'decimal:2',
            'quantity_available' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Listing $listing): void {
            if (filled($listing->image_path)) {
                ListingStorage::disk()->delete($listing->image_path);
            }
        });
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        return ListingStorage::disk()->url($this->image_path);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->farmer_seller_id === $user->id;
    }

    /**
     * Active listings from active farmer-sellers, visible on the buyer marketplace.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeMarketplaceVisible(Builder $query): Builder
    {
        return $query
            ->where('listings.is_active', true)
            ->whereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('is_active', true))
            ->whereHas('category', fn (Builder $category): Builder => $category->where('is_active', true));
    }
}
