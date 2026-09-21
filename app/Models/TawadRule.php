<?php

namespace App\Models;

use App\Enums\TawadType;
use Database\Factories\TawadRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tawad is a seller-published peso discount rule, not live negotiation,
 * because the system records transactions but does not mediate them.
 *
 * Peso amounts only. There is no percentage input anywhere in this system.
 */
#[Fillable([
    'listing_id',
    'type',
    'discount_amount',
    'min_quantity',
    'is_active',
    'ended_at',
])]
class TawadRule extends Model
{
    /** @use HasFactory<TawadRuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TawadType::class,
            'discount_amount' => 'decimal:2',
            'min_quantity' => 'decimal:2',
            'is_active' => 'boolean',
            'ended_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function appliesTo(float $quantity): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($this->type) {
            TawadType::Flat => $quantity > 0,
            TawadType::MinimumQuantity => $this->min_quantity !== null
                && $quantity >= (float) $this->min_quantity,
        };
    }

    /**
     * The peso amount taken off the line. A rule that does not apply gives
     * zero, never a negative number.
     */
    public function discountFor(float $quantity): float
    {
        return $this->appliesTo($quantity) ? (float) $this->discount_amount : 0.0;
    }

    /**
     * Floor check against the crop type's system floor. Farm-blind.
     *
     * Kept so existing callers keep working until Pass 2C repoints them at
     * keepsUnitPriceAbove() with the farm's effective floor.
     */
    public function keepsUnitPriceAboveFloor(Listing $listing, CropType $cropType): bool
    {
        return $this->keepsUnitPriceAbove($listing, (float) $cropType->floor_price);
    }

    /**
     * Floor check, by type, against an explicit floor. A flat rule is tested
     * against a one-unit order, which is its worst case; a minimum-quantity
     * rule is tested at exactly its threshold. Run this at rule creation AND
     * again at checkout.
     *
     * Pass the farm's effective floor from PriceGuardResolver. The stranded
     * listing flag also calls this twice, with the floor before and after a
     * raise, to tell whether the raise is what broke the rule.
     */
    public function keepsUnitPriceAbove(Listing $listing, float $floor): bool
    {
        $unitPrice = (float) $listing->price_per_unit;
        $discount = (float) $this->discount_amount;

        $quantity = match ($this->type) {
            TawadType::Flat => 1.0,
            TawadType::MinimumQuantity => (float) ($this->min_quantity ?? 0),
        };

        if ($quantity <= 0) {
            return false;
        }

        return ((($unitPrice * $quantity) - $discount) / $quantity) >= $floor;
    }

    /**
     * @param  Builder<TawadRule>  $query
     * @return Builder<TawadRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('tawad_rules.is_active', true);
    }
}
