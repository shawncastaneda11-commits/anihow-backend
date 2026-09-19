<?php

namespace App\Enums;

enum OrderActor: string
{
    case Buyer = 'buyer';
    case FarmerSeller = 'farmer_seller';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::FarmerSeller => 'Farmer-Seller',
        };
    }
}
