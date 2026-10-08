<?php

namespace App\Enums;

enum CostCategory: string
{
    case Seeds = 'seeds';
    case Fertilizer = 'fertilizer';
    case Pesticide = 'pesticide';
    case Labor = 'labor';
    case Transport = 'transport';
    case Packaging = 'packaging';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Seeds => 'Seeds',
            self::Fertilizer => 'Fertilizer',
            self::Pesticide => 'Pesticide',
            self::Labor => 'Labor',
            self::Transport => 'Transport',
            self::Packaging => 'Packaging',
            self::Other => 'Other',
        };
    }
}
