<?php

namespace App\Rules;

use App\Models\CropType;
use App\Support\Pricing\PriceGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A farm discount ceiling override may only lower the ceiling. Tighten-only,
 * downward side.
 */
class AtOrBelowSystemMaximum implements ValidationRule
{
    public function __construct(private readonly CropType $cropType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (PriceGuard::centavos($value) > PriceGuard::centavos($this->cropType->max_discount)) {
            $fail('The farm maximum peso discount for :attribute cannot exceed the system maximum of PHP '
                .number_format((float) $this->cropType->max_discount, 2).'.');
        }
    }
}
