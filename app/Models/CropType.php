<?php

namespace App\Models;

use App\Enums\ListingUnit;
use App\Observers\CropTypeObserver;
use Database\Factories\CropTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Crop types belong to one farm. A farm with its own rows uses only those.
 * Rows with no farm are the shared catalog, used only by farms that have
 * not added their own yet.
 *
 * A farm may tighten floor_price and max_discount for itself, never loosen
 * them. PriceGuardResolver is what listing, tawad, checkout, and walk-in
 * validation compare against.
 *
 * Raising floor_price notifies every seller whose listing it newly strands.
 * See CropTypeObserver.
 */
#[ObservedBy([CropTypeObserver::class])]
#[Fillable([
    'name',
    'slug',
    'label_en',
    'label_fil',
    'description',
    'unit_of_measure',
    'floor_price',
    'max_discount',
    'is_active',
    'farm_id',
    'created_by',
])]
class CropType extends Model
{
    /** @use HasFactory<CropTypeFactory> */
    use HasFactory;

    /**
     * Kilograms when a crop type is created without a unit. The CMS asks for
     * the unit of the floor price, which decides which units a seller may use.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit_of_measure' => 'kg',
    ];

    protected function casts(): array
    {
        return [
            'unit_of_measure' => ListingUnit::class,
            'floor_price' => 'decimal:4',
            'max_discount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CropType $cropType): void {
            $cropType->detachForDeletion();
        });
    }

    /**
     * Listings and farm price overrides cannot outlive the crop type they
     * point at. Order lines stay: their item name, unit, and prices are
     * already snapshotted, and crop_type_id is cleared by the foreign key.
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    private function detachForDeletion(): void
    {
        $this->farmOverrides()->delete();

        $this->listings()->withTrashed()->pluck('id')->each(function (int|string $listingId): void {
            $listing = Listing::withTrashed()->find($listingId);

            if ($listing === null) {
                return;
            }

            Report::query()
                ->where('reportable_type', $listing->getMorphClass())
                ->where('reportable_id', $listing->getKey())
                ->delete();

            $listing->photos()->get()->each(function (ListingPhoto $photo): void {
                $photo->delete();
            });

            $listing->forceDelete();
        });
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Farms that have tightened this entry's floor or discount ceiling for
     * themselves. Absence means the farm sits on the system values.
     */
    public function farmOverrides(): HasMany
    {
        return $this->hasMany(FarmCropTypeOverride::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(CropCareArticle::class, 'article_crop_type');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Bilingual scope is crop labels only. Do not widen this.
     */
    public function label(string $locale = 'en'): string
    {
        return $locale === 'fil' ? $this->label_fil : $this->label_en;
    }

    /**
     * System-level check, farm-blind. Correct for Super Admin system-wide
     * views and for validating a farm override against its outer bound.
     *
     * Not correct for a listing, a tawad rule, or a checkout line: those
     * resolve against the farm's effective floor through PriceGuardResolver,
     * which is at or above this one. See Pass 2C.
     */
    public function allowsPrice(float $price): bool
    {
        return $price >= (float) $this->floor_price;
    }

    /**
     * System-level check, farm-blind. Same caveat as allowsPrice().
     */
    public function allowsDiscount(float $amount): bool
    {
        return $amount > 0 && $amount <= (float) $this->max_discount;
    }

    /**
     * @param  Builder<CropType>  $query
     * @return Builder<CropType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('crop_types.is_active', true);
    }

    /**
     * A farm that has added its own crop types sees only those. A farm that
     * has not still sees the shared catalog.
     *
     * @param  Builder<CropType>  $query
     * @return Builder<CropType>
     */
    public function scopeForFarm(Builder $query, int|string|null $farmId): Builder
    {
        if ($farmId !== null && static::query()->where('farm_id', $farmId)->exists()) {
            return $query->where('crop_types.farm_id', $farmId);
        }

        return $query->whereNull('crop_types.farm_id');
    }
}
