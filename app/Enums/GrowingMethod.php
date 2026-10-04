<?php

namespace App\Enums;

enum GrowingMethod: string
{
    case CertifiedOrganic = 'certified_organic';
    case NaturallyGrown = 'naturally_grown';

    public function label(): string
    {
        return match ($this) {
            self::CertifiedOrganic => 'Certified organic',
            self::NaturallyGrown => 'Naturally grown',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method): array => [$method->value => $method->label()])
            ->all();
    }
}
