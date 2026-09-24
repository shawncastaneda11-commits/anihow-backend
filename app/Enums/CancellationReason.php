<?php

namespace App\Enums;

enum CancellationReason: string
{
    case BuyerCancelled = 'buyer_cancelled';
    case SellerDeclined = 'seller_declined';
    case NoShow = 'no_show';
    case SellerUnresponsive = 'seller_unresponsive';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BuyerCancelled => 'Cancelled by buyer',
            self::SellerDeclined => 'Declined by farmer-seller',
            self::NoShow => 'No-show at handover',
            self::SellerUnresponsive => 'Seller unresponsive',
            self::Other => 'Other',
        };
    }

    /**
     * Reasons a buyer may select. A buyer can only cancel before Confirmed.
     *
     * @return list<self>
     */
    public static function forBuyer(): array
    {
        return [self::BuyerCancelled, self::Other];
    }

    /**
     * @return list<self>
     */
    public static function forSeller(): array
    {
        return [self::SellerDeclined, self::NoShow, self::Other];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->label()])
            ->all();
    }
}
