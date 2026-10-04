<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Converted => 'Converted',
            self::Cancelled => 'Cancelled',
        };
    }
}
