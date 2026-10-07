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
    case Milliliter = 'ml';
    case Dozen = 'dozen';
    case Pack = 'pack';
    case Bottle = 'bottle';

    /**
     * Count and package units are sold whole. The name is the plural used
     * in "Trays are sold whole."
     */
    public function wholeSaleName(): string
    {
        return match ($this) {
            self::Piece => 'Pieces',
            self::Bundle => 'Bundles',
            self::Sack => 'Sacks',
            self::Tray => 'Trays',
            self::Dozen => 'Dozens',
            self::Pack => 'Packs',
            self::Bottle => 'Bottles',
            default => $this->label(),
        };
    }

    public function sellsWhole(): bool
    {
        return $this->family() === UnitFamily::Count
            || $this->family() === UnitFamily::Package;
    }

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilogram (kg)',
            self::Gram => 'Gram (g)',
            self::Piece => 'Piece',
            self::Bundle => 'Bundle',
            self::Sack => 'Sack',
            self::Tray => 'Tray',
            self::Liter => 'Litre (L)',
            self::Milliliter => 'Millilitre (mL)',
            self::Dozen => 'Dozen',
            self::Pack => 'Pack',
            self::Bottle => 'Bottle',
        };
    }

    public function family(): UnitFamily
    {
        return match ($this) {
            self::Kilogram, self::Gram => UnitFamily::Weight,
            self::Liter, self::Milliliter => UnitFamily::Volume,
            self::Piece, self::Dozen => UnitFamily::Count,
            self::Bundle, self::Sack, self::Tray, self::Pack, self::Bottle => UnitFamily::Package,
        };
    }

    /**
     * How many of the family's base unit one of this unit represents.
     * Weight base is kg, volume base is L, count base is piece.
     * Package units do not convert, so each is its own base.
     */
    public function baseFactor(): float
    {
        return (float) $this->baseFactorSql();
    }

    public function baseFactorSql(): string
    {
        return match ($this) {
            self::Gram, self::Milliliter => '0.001',
            self::Dozen => '12',
            default => '1',
        };
    }

    public function baseUnit(): self
    {
        return match ($this->family()) {
            UnitFamily::Weight => self::Kilogram,
            UnitFamily::Volume => self::Liter,
            UnitFamily::Count => self::Piece,
            UnitFamily::Package => $this,
        };
    }

    /**
     * Conversion is allowed only inside a family. Package units match only themselves.
     */
    public function convertsTo(self $other): bool
    {
        if ($this->family() !== $other->family()) {
            return false;
        }

        if ($this->family() === UnitFamily::Package) {
            return $this === $other;
        }

        return true;
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
