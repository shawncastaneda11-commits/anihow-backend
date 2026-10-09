<?php

namespace App\Enums;

enum ReservationCancellationReason: string
{
    case BuyerCancelled = 'buyer_cancelled';
    case SellerCancelled = 'seller_cancelled';
    case HarvestShortfall = 'harvest_shortfall';
    case ListingRemoved = 'listing_removed';
    case AccountClosed = 'account_closed';
    case PaymentExpired = 'payment_expired';

    public function label(): string
    {
        return match ($this) {
            self::BuyerCancelled => 'Buyer cancelled',
            self::SellerCancelled => 'Seller cancelled',
            self::HarvestShortfall => 'Harvest shortfall',
            self::ListingRemoved => 'Listing removed',
            self::AccountClosed => 'Account closed',
            self::PaymentExpired => 'Payment time expired',
        };
    }
}
