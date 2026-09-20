<?php

namespace App\Rules;

use App\Models\CropType;
use App\Support\Pricing\PriceGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A farm floor override may only raise the floor. Tighten-only, upward side.
 */
class AtOrAboveSystemFloor implements ValidationRule
{
    public function __construct(private readonly CropType $cropType)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (PriceGuard::centavos($value) < PriceGuard::centavos($this->cropType->floor_price)) {
            $fail('The farm floor price for :attribute cannot be lower than the system floor of PHP '
                . number_format((float) $this->cropType->floor_price, 2) . '.');
        }
    }
}
