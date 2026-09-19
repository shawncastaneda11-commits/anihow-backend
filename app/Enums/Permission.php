<?php

namespace App\Enums;

enum Permission: string
{
    // Accounts
    case ManageAccounts = 'manage_accounts';
    case ApproveFarmerSeller = 'approve_farmer_seller';
    case SuspendAccounts = 'suspend_accounts';

    // Farms
    case ManageFarms = 'manage_farms';
    case ManageOwnFarmProfile = 'manage_own_farm_profile';
    case ViewOwnFarmRoster = 'view_own_farm_roster';

    // Crop taxonomy
    case ManageCropTypes = 'manage_crop_types';
    case SetCropPricing = 'set_crop_pricing';

    // Listings
    case ManageOwnListings = 'manage_own_listings';
    case TakedownListings = 'takedown_listings';

    // Tawad
    case ManageOwnTawadRules = 'manage_own_tawad_rules';

    // Marketplace and orders
    case BrowseMarketplace = 'browse_marketplace';
    case PlaceOrders = 'place_orders';
    case ManageOwnOrders = 'manage_own_orders';
    case ViewAllOrders = 'view_all_orders';

    // Reviews and reports
    case WriteReviews = 'write_reviews';
    case ModerateReviews = 'moderate_reviews';
    case SubmitReports = 'submit_reports';
    case ResolveReports = 'resolve_reports';

    // Crop-care reference
    case ManageOwnFarmArticles = 'manage_own_farm_articles';
    case ManageAllArticles = 'manage_all_articles';

    // Descriptive analytics
    case ViewSystemAnalytics = 'view_system_analytics';
    case ViewFarmAnalytics = 'view_farm_analytics';
    case ViewOwnAnalytics = 'view_own_analytics';
    case GenerateExports = 'generate_exports';

    public function label(): string
    {
        return match ($this) {
            self::ManageAccounts => 'Manage accounts',
            self::ApproveFarmerSeller => 'Approve farmer-seller registrations',
            self::SuspendAccounts => 'Suspend accounts',
            self::ManageFarms => 'Manage farms',
            self::ManageOwnFarmProfile => 'Manage own farm profile',
            self::ViewOwnFarmRoster => 'View own farm roster',
            self::ManageCropTypes => 'Manage crop taxonomy',
            self::SetCropPricing => 'Set floor price and maximum discount',
            self::ManageOwnListings => 'Manage own listings',
            self::TakedownListings => 'Take down listings',
            self::ManageOwnTawadRules => 'Manage own tawad rules',
            self::BrowseMarketplace => 'Browse marketplace',
            self::PlaceOrders => 'Place orders',
            self::ManageOwnOrders => 'Manage own orders',
            self::ViewAllOrders => 'View all orders',
            self::WriteReviews => 'Write reviews',
            self::ModerateReviews => 'Moderate reviews',
            self::SubmitReports => 'Submit reports',
            self::ResolveReports => 'Resolve reports',
            self::ManageOwnFarmArticles => 'Manage own farm crop-care articles',
            self::ManageAllArticles => 'Manage all crop-care articles',
            self::ViewSystemAnalytics => 'View system-wide analytics',
            self::ViewFarmAnalytics => 'View farm analytics',
            self::ViewOwnAnalytics => 'View own analytics',
            self::GenerateExports => 'Generate exports',
        };
    }

    /**
     * Single source of truth for the role seeder.
     *
     * Every Role case must have an arm here. This match is intentionally
     * exhaustive with no default, so adding a role without deciding its
     * permissions fails loudly at boot rather than silently granting nothing.
     *
     * @return list<self>
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => self::cases(),

            Role::ContentEditor => [
                self::ManageOwnFarmProfile,
                self::ViewOwnFarmRoster,
                self::ManageOwnFarmArticles,
                self::ViewFarmAnalytics,
            ],

            Role::FarmerSeller => [
                self::ManageOwnListings,
                self::ManageOwnTawadRules,
                self::ManageOwnOrders,
                self::ViewOwnAnalytics,
                self::SubmitReports,
            ],

            Role::Buyer => [
                self::BrowseMarketplace,
                self::PlaceOrders,
                self::WriteReviews,
                self::SubmitReports,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $permission): array => [$permission->value => $permission->label()])
            ->all();
    }
}
