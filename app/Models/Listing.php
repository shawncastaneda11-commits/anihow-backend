<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Support\ListingStorage;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The farmer-seller's own object, created under a crop taxonomy entry.
 * Own photos, own copy, own price, own stock. The taxonomy is a category
 * tree, not a product list.
 */
#[Fillable([
    'farmer_seller_id',
    'farm_id',
    'crop_type_id',
    'title',
    'description',
    'price_per_unit',
    'quantity_available',
    'quantity_held',
    'image_path',
    'is_active',
    'status',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
            'quantity_available' => 'decimal:2',
            'quantity_held' => 'decimal:2',
            'is_active' => 'boolean',
            'status' => ListingStatus::class,
            'taken_down_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // forceDeleted, not deleting. With SoftDeletes, `deleting` fires on a
        // soft delete and would destroy the image of a listing that is still
        // recoverable and still referenced by order items.
        static::forceDeleted(function (Listing $listing): void {
            if (filled($listing->image_path)) {
                ListingStorage::disk()->delete($listing->image_path);
            }
        });
    }

    public function farmerSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_seller_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function cropType(): BelongsTo
    {
        return $this->belongsTo(CropType::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort_order');
    }

    public function tawadRules(): HasMany
    {
        return $this->hasMany(TawadRule::class);
    }

    public function activeTawadRule(): HasOne
    {
        return $this->hasOne(TawadRule::class)->where('is_active', true);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function takenDownBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_down_by');
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
     * Stock held by Placed orders is not yet deducted but is not sellable
     * either. Never order against quantity_available directly.
     */
    public function sellableQuantity(): float
    {
        return (float) $this->quantity_available - (float) $this->quantity_held;
    }

    public function hasStockFor(float $quantity): bool
    {
        return $quantity > 0 && $quantity <= $this->sellableQuantity();
    }

    /**
     * True when the Super Admin has raised the crop type's floor above this
     * listing's price. The listing is stranded: it is not auto-corrected,
     * because the system must never move a farmer's price for them.
     */
    public function isBelowFloor(): bool
    {
        return (float) $this->price_per_unit < (float) $this->cropType->floor_price;
    }

    /**
     * Published listings, from active sellers, under active crop types.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeMarketplaceVisible(Builder $query): Builder
    {
        return $query
            ->where('listings.status', ListingStatus::Published)
            ->where('listings.is_active', true)
            ->whereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('status', UserStatus::Active))
            ->whereHas('cropType', fn (Builder $cropType): Builder => $cropType->where('is_active', true));
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('listings.farm_id', $farmId);
    }
}
