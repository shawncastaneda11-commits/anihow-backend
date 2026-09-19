<?php

namespace App\Models;

use App\Enums\ListingUnit;
use Database\Factories\CropTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The shared, system-wide crop taxonomy. Not farm-scoped: one kamatis entry
 * governs every kamatis listing regardless of which farm posted it. If each
 * farm set its own floor, the floor would stop being a guardrail.
 *
 * floor_price and max_discount are writable by the Super Admin only. Guard
 * them in the policy, not just in the form.
 */
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
    'created_by',
])]
class CropType extends Model
{
    /** @use HasFactory<CropTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_of_measure' => ListingUnit::class,
            'floor_price' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(CropCareArticle::class, 'article_crop_type');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Bilingual scope is crop labels only. Do not widen this to other content.
     */
    public function label(string $locale = 'en'): string
    {
        return $locale === 'fil' ? $this->label_fil : $this->label_en;
    }

    public function allowsPrice(float $price): bool
    {
        return $price >= (float) $this->floor_price;
    }

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
}
