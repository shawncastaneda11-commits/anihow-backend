<?php

namespace App\Support\Pricing;

use App\Enums\ListingUnit;

/**
 * The two numbers every price decision in the system resolves against.
 *
 * floor    the effective floor price for one farm and one crop type
 * ceiling  the effective maximum peso discount per unit for one farm and one
 *          crop type, or null when no maximum is set. With no maximum, the
 *          floor alone limits a tawad.
 *
 * unit     the unit both are in: the crop's main unit, or the unit of the
 *          extra row that covers the listing (see CropTypeUnitGuard)
 *
 * Callers convert a listing price into this unit first. All comparisons run in centavos so that
 * 2dp peso amounts never lose a comparison to binary float representation.
 */
final class PriceGuard
{
    public function __construct(
        public readonly float $floor,
        public readonly ?float $ceiling,
        public readonly ?ListingUnit $unit = null,
    ) {}

    /** A listing price is legal at or above the effective floor. */
    public function allowsPrice(float|string $price): bool
    {
        return self::centavos($price) >= self::centavos($this->floor);
    }

    public function hasCeiling(): bool
    {
        return $this->ceiling !== null;
    }

    /**
     * A per-unit tawad amount is legal above zero and, when a maximum is set,
     * at or below it. A maximum of zero turns tawad off for the crop.
     */
    public function allowsDiscount(float|string $amount): bool
    {
        $centavos = self::centavos($amount);

        if ($centavos <= 0) {
            return false;
        }

        return $this->ceiling === null || $centavos <= self::centavos($this->ceiling);
    }

    /** The resulting unit price after a discount may never fall below the effective floor. */
    public function allowsResultingUnitPrice(float|string $unitPrice, float|string $discount): bool
    {
        return (self::centavos($unitPrice) - self::centavos($discount)) >= self::centavos($this->floor);
    }

    /** The ceiling as text for messages, such as "15.00". Only call when one is set. */
    public function ceilingText(): string
    {
        return number_format((float) $this->ceiling, 2, '.', '');
    }

    public static function centavos(float|string $peso): int
    {
        return (int) round(((float) $peso) * 100);
    }

    /**
     * Orders two ceilings where null means no maximum, so null is the
     * loosest. Returns true when $next is stricter than $previous.
     */
    public static function ceilingTightened(?float $previous, ?float $next): bool
    {
        if ($next === null) {
            return false;
        }

        return $previous === null || self::centavos($next) < self::centavos($previous);
    }
}
