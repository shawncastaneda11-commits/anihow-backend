<?php

namespace App\Support\Pricing;

/**
 * The two numbers every price decision in the system resolves against.
 *
 * floor    the effective floor price for one farm and one crop type
 * ceiling  the effective maximum peso discount for one farm and one crop type
 *
 * All comparisons run in centavos so that 2dp peso amounts never lose a
 * comparison to binary float representation.
 */
final class PriceGuard
{
    public function __construct(
        public readonly float $floor,
        public readonly float $ceiling,
    ) {}

    /** A listing price is legal at or above the effective floor. */
    public function allowsPrice(float|string $price): bool
    {
        return self::centavos($price) >= self::centavos($this->floor);
    }

    /**
     * A tawad rule amount is legal above zero and at or below the effective
     * ceiling. The positivity half matches CropType::allowsDiscount(), so a
     * call site repointed from the crop type to the guard keeps rejecting a
     * zero or negative discount.
     */
    public function allowsDiscount(float|string $amount): bool
    {
        $centavos = self::centavos($amount);

        return $centavos > 0 && $centavos <= self::centavos($this->ceiling);
    }

    /** The resulting unit price after a discount may never fall below the effective floor. */
    public function allowsResultingUnitPrice(float|string $unitPrice, float|string $discount): bool
    {
        return (self::centavos($unitPrice) - self::centavos($discount)) >= self::centavos($this->floor);
    }

    public static function centavos(float|string $peso): int
    {
        return (int) round(((float) $peso) * 100);
    }
}
