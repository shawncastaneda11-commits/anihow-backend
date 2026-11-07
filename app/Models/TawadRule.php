<?php

namespace App\Models;

use App\Enums\TawadType;
use App\Models\Concerns\LogsCmsActivity;
use App\Support\Pricing\PriceGuard;
use App\Support\Pricing\UnitConverter;
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

    use LogsCmsActivity;

    protected function casts(): array
    {
        return [
            'type' => TawadType::class,
            'discount_amount' => 'decimal:4',
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
     * Floor check against the crop type's system floor. Farm-blind. Kept for
     * the smoke-test seeder; enforcement uses withinGuard().
     */
    public function keepsUnitPriceAboveFloor(Listing $listing, CropType $cropType): bool
    {
        return $this->keepsUnitPriceAbove($listing, (float) $cropType->floor_price);
    }

    /**
     * How many listing units the discount is spread over when it is judged.
     * A minimum-quantity rule applies from its threshold, so it is judged
     * there. A flat rule applies to any order, so it is judged on the
     * smallest order a buyer can place: the listing's minimum order.
     */
    public function quantityBasis(Listing $listing): float
    {
        if ($this->type === TawadType::MinimumQuantity) {
            return (float) ($this->min_quantity ?? 0);
        }

        $minimum = (float) ($listing->min_order_quantity ?? 0);

        return $minimum > 0 ? $minimum : 1.0;
    }

    /**
     * Why this rule does not fit the guard, or null when it does.
     *
     * The tawad is pesos off the whole line, so it is first spread over the
     * quantity it applies to, giving pesos off each listing unit. That
     * per-unit amount and the listing price are then converted into the crop
     * type's unit, where the guard lives, and compared: the per-unit amount
     * against the maximum tawad (when one is set), and the price after the
     * tawad against the floor. Run when a rule is created and again at
     * checkout and walk-in, so the three always agree.
     *
     * @return 'unit'|'ceiling'|'floor'|null
     */
    public function guardProblem(Listing $listing, PriceGuard $guard): ?string
    {
        $perUnit = $this->perUnitInCropUnit($listing);
        $price = $this->priceInCropUnit($listing);

        if ($perUnit === null || $price === null) {
            $listing->loadMissing('cropType');
            $cropType = $listing->cropType;

            return $cropType !== null && app(UnitConverter::class)->isGuarded($cropType, $listing->farm_id)
                ? 'unit'
                : null;
        }

        if (! $guard->allowsDiscount($perUnit)) {
            return 'ceiling';
        }

        if (! $guard->allowsResultingUnitPrice($price, $perUnit)) {
            return 'floor';
        }

        return null;
    }

    public function withinGuard(Listing $listing, PriceGuard $guard): bool
    {
        return $this->guardProblem($listing, $guard) === null;
    }

    /**
     * Floor check against an explicit floor, on the same per-unit basis as
     * guardProblem(). The stranded-listing flag calls this twice, with the
     * floor before and after a raise, to tell whether the raise is what broke
     * the rule.
     */
    public function keepsUnitPriceAbove(Listing $listing, float $floor): bool
    {
        $perUnit = $this->perUnitInCropUnit($listing);
        $price = $this->priceInCropUnit($listing);

        if ($perUnit === null || $price === null) {
            return $floor <= 0;
        }

        return (new PriceGuard($floor, null))->allowsResultingUnitPrice($price, $perUnit);
    }

    /**
     * Pesos off one listing unit, converted into the crop type's unit, or
     * null when the units cannot convert or the rule has no quantity.
     */
    public function perUnitInCropUnit(Listing $listing): ?float
    {
        $basis = $this->quantityBasis($listing);

        if ($basis <= 0) {
            return null;
        }

        return $this->inCropUnit($listing, (float) $this->discount_amount / $basis);
    }

    private function priceInCropUnit(Listing $listing): ?float
    {
        return $this->inCropUnit($listing, (float) $listing->price_per_unit);
    }

    /**
     * A per-unit peso amount in the listing's unit, moved into the unit the
     * guard is in: the crop type's main unit, or its extra row's unit.
     */
    private function inCropUnit(Listing $listing, float $perListingUnit): ?float
    {
        $listing->loadMissing('cropType');
        $cropType = $listing->cropType;

        if ($cropType?->unit_of_measure === null) {
            return $perListingUnit;
        }

        $guardUnit = $cropType->guardUnitFor($listing->unit);

        return app(UnitConverter::class)->guardPrice($listing->unit ?? $guardUnit, $guardUnit, $perListingUnit);
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
