<?php

namespace App\Enums;

enum StockRemovalReason: string
{
    case Spoiled = 'spoiled';
    case Damaged = 'damaged';
    case SoldOutside = 'sold_outside';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Spoiled => 'Spoiled',
            self::Damaged => 'Damaged',
            self::SoldOutside => 'Sold outside the app',
            self::Correction => 'Correction',
        };
    }
}
