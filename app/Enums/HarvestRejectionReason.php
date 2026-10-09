<?php

namespace App\Enums;

enum HarvestRejectionReason: string
{
    case Pests = 'pests';
    case BruisedDamaged = 'bruised_damaged';
    case Undersized = 'undersized';
    case Spoiled = 'spoiled';
    case Other = 'other';
    case Defective = 'defective';
    case PackagingDamaged = 'packaging_damaged';

    public function label(): string
    {
        return match ($this) {
            self::Pests => 'Pests',
            self::BruisedDamaged => 'Bruised or damaged',
            self::Undersized => 'Undersized',
            self::Spoiled => 'Spoiled',
            self::Other => 'Other',
            self::Defective => 'Defective',
            self::PackagingDamaged => 'Packaging damaged',
        };
    }

    /**
     * @return list<self>
     */
    public static function forFresh(): array
    {
        return [
            self::Pests,
            self::BruisedDamaged,
            self::Undersized,
            self::Spoiled,
            self::Other,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forValueAdded(): array
    {
        return [
            self::Defective,
            self::PackagingDamaged,
            self::Spoiled,
            self::Other,
        ];
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(bool $valueAdded): array
    {
        $reasons = $valueAdded ? self::forValueAdded() : self::forFresh();

        return array_map(fn (self $reason): string => $reason->value, $reasons);
    }
}
