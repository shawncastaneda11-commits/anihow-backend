<?php

namespace App\Models;

use App\Enums\GrowingMethod;
use App\Enums\ListingStatus;
use App\Enums\ListingUnit;
use App\Enums\UserStatus;
use App\Support\ImageVariants;
use App\Support\Pricing\PriceGuard;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
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
    'unit',
    'title',
    'description',
    'price_per_unit',
    'quantity_available',
    'quantity_held',
    'image_path',
    'is_active',
    'status',
    'available_from',
    'available_until',
    'harvested_on',
    'growing_method',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'unit' => ListingUnit::class,
            'price_per_unit' => 'decimal:4',
            'quantity_available' => 'decimal:2',
            'quantity_held' => 'decimal:2',
            'is_active' => 'boolean',
            'status' => ListingStatus::class,
            'taken_down_at' => 'datetime',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'harvested_on' => 'date',
            'growing_method' => GrowingMethod::class,
        ];
    }

    /**
     * The badge is decided when the listing is read. An expired certificate
     * drops the certified badge and leaves the stored method unchanged.
     */
    public function organicBadge(): ?string
    {
        if ($this->growing_method === GrowingMethod::CertifiedOrganic) {
            return $this->farm?->isOrganicCertified() ? 'certified' : null;
        }

        if ($this->growing_method === GrowingMethod::NaturallyGrown) {
            return 'naturally_grown';
        }

        return null;
    }

    protected static function booted(): void
    {
        // forceDeleted, not deleting. With SoftDeletes, `deleting` fires on a
        // soft delete and would destroy the image of a listing that is still
        // recoverable and still referenced by order items.
        static::forceDeleted(function (Listing $listing): void {
            app(ImageVariants::class)->delete($listing->image_path);
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
        return app(ImageVariants::class)->url($this->image_path);
    }

    public function thumbnailUrl(): ?string
    {
        return app(ImageVariants::class)->thumbnailUrl($this->image_path);
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
     * The floor and discount ceiling this listing is actually held to: the
     * system values, tightened by its farm where the farm has done so.
     *
     * In a table or loop, eager load cropType and farm.cropTypeOverrides first,
     * or this issues queries per row.
     */
    public function priceGuard(): PriceGuard
    {
        $resolver = app(PriceGuardResolver::class);

        return $this->farm !== null
            ? $resolver->for($this->farm, $this->cropType)
            : $resolver->system($this->cropType);
    }

    public function effectiveFloor(): float
    {
        return $this->priceGuard()->floor;
    }

    /**
     * True when a floor has risen above this listing's price, whether the
     * Super Admin raised the system floor or the farm raised its own. The
     * listing is stranded: it is not auto-corrected, because the system must
     * never move a farmer's price for them.
     */
    public function isBelowFloor(): bool
    {
        $converter = app(UnitConverter::class);
        $price = $converter->guardPrice(
            $this->unit,
            $this->cropType->unit_of_measure,
            $this->price_per_unit,
        );

        if ($price === null) {
            return $converter->isGuarded($this->cropType, $this->farm_id);
        }

        return ! $this->priceGuard()->allowsPrice($price);
    }

    /**
     * On sale at this moment. A null start means available now. A null end
     * means the listing does not expire. The end instant itself is already over.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeAvailableNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(function (Builder $window) use ($now): void {
                $window->whereNull('listings.available_from')
                    ->orWhere('listings.available_from', '<=', $now);
            })
            ->where(function (Builder $window) use ($now): void {
                $window->whereNull('listings.available_until')
                    ->orWhere('listings.available_until', '>', $now);
            });
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $window) use ($now): void {
            $window->whereNull('listings.available_until')
                ->orWhere('listings.available_until', '>', $now);
        });
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->notExpired()
            ->whereNotNull('listings.available_from')
            ->where('listings.available_from', '>', now());
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('listings.available_until')
            ->where('listings.available_until', '<=', now());
    }

    public function isUpcoming(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return $this->available_from !== null && $this->available_from->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->available_until !== null && ! $this->available_until->isFuture();
    }

    public function availabilityState(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isUpcoming()) {
            return 'upcoming';
        }

        return 'available';
    }

    /**
     * Published listings, from active sellers, under active crop types, whose
     * availability window has not ended. An ended window leaves the market the
     * same way a taken-down listing does: checkout drops the cart line.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeMarketplaceVisible(Builder $query): Builder
    {
        return $query
            ->where('listings.status', ListingStatus::Published)
            ->where('listings.is_active', true)
            ->notExpired()
            ->whereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('status', UserStatus::Active))
            ->whereHas('cropType', fn (Builder $cropType): Builder => $cropType->where('is_active', true));
    }

    /**
     * A listing a buyer is allowed to know about: published, active, from an
     * active seller, under an active crop type, and in a unit that crop
     * accepts. The availability window is a separate question.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeListedForBuyers(Builder $query): Builder
    {
        return $query
            ->where('listings.status', ListingStatus::Published)
            ->where('listings.is_active', true)
            ->whereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('status', UserStatus::Active))
            ->whereHas('cropType', fn (Builder $cropType): Builder => $cropType->where('is_active', true))
            ->whereDoesntHave(
                'cropType',
                fn (Builder $cropType): Builder => $cropType->whereRaw(UnitConverter::incompatibleGuardSql()),
            );
    }

    /**
     * The buyer catalogue. A published listing whose unit cannot convert into
     * a guarded crop is stranded and stays off the market until the seller
     * picks an allowed unit. An ended window is off the catalogue too.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function scopeBuyerVisible(Builder $query): Builder
    {
        return $query->listedForBuyers()->notExpired();
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
