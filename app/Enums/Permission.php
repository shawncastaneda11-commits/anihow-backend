<?php

namespace App\Enums;

enum Permission: string
{
    case ManageAccounts = 'manage_accounts';
    case CreateFarmerSeller = 'create_farmer_seller';
    case OverseeMarketplace = 'oversee_marketplace';
    case OverseeListings = 'oversee_listings';
    case OverseeCropCare = 'oversee_crop_care';
    case ManageOwnListings = 'manage_own_listings';
    case RecordPosSales = 'record_pos_sales';
    case ViewSalesAnalytics = 'view_sales_analytics';
    case ManageCropCare = 'manage_crop_care';
    case BrowseMarketplace = 'browse_marketplace';
    case ReserveProduce = 'reserve_produce';

    /**
     * @return list<self>
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => self::cases(),
            Role::FarmerSeller => [
                self::ManageOwnListings,
                self::RecordPosSales,
                self::ViewSalesAnalytics,
                self::ManageCropCare,
            ],
            Role::Buyer => [
                self::BrowseMarketplace,
                self::ReserveProduce,
            ],
        };
    }
}
