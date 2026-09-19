<?php

namespace App\Enums;

enum FulfillmentPreference: string
{
    case BuyerPickup = 'buyer_pickup';
    case SellerDelivers = 'seller_delivers';

    public function label(): string
    {
        return match ($this) {
            self::BuyerPickup => 'Buyer picks up',
            self::SellerDelivers => 'Farmer-seller delivers',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $preference): array => [$preference->value => $preference->label()])
            ->all();
    }
}
