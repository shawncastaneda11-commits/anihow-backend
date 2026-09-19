<?php

namespace App\Enums;

enum TawadType: string
{
    case Flat = 'flat';
    case MinimumQuantity = 'min_quantity';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat peso discount',
            self::MinimumQuantity => 'Peso discount at minimum quantity',
        };
    }

    public function requiresMinimumQuantity(): bool
    {
        return $this === self::MinimumQuantity;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
