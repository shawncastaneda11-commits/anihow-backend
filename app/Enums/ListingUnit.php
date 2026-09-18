<?php

namespace App\Enums;

enum ListingUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Piece = 'piece';
    case Bundle = 'bundle';
    case Sack = 'sack';
    case Tray = 'tray';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilogram (kg)',
            self::Gram => 'Gram (g)',
            self::Piece => 'Piece',
            self::Bundle => 'Bundle',
            self::Sack => 'Sack',
            self::Tray => 'Tray',
            self::Liter => 'Liter',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->label()])
            ->all();
    }
}
