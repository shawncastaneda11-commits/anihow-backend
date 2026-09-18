<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case FarmerSeller = 'farmer_seller';
    case Buyer = 'buyer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::FarmerSeller => 'Farmer-Seller',
            self::Buyer => 'Buyer',
        };
    }
}
