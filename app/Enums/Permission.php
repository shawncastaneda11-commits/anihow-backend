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

    // Farm price guards, tighten-only. SetCropPricing is the system layer on
    // the taxonomy entry; SetFarmPricing is the farm layer beneath it. Which
    // farms a holder reaches is decided by ManageFarms, as in FarmResource.
    case SetFarmPricing = 'set_farm_pricing';

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

    // Walk-in sales: the farmer-seller records an in-person sale to someone
    // with no buyer account. Deliberately not PlaceOrders, which stays with the
    // Buyer role: one account, one role. Decision 22.
    case RecordWalkInSales = 'record_walk_in_sales';

    // Reviews and reports
    case WriteReviews = 'write_reviews';
    case ModerateReviews = 'moderate_reviews';
    case SubmitReports = 'submit_reports';
    case ResolveReports = 'resolve_reports';

    // Crop-care reference
    case ManageOwnFarmArticles = 'manage_own_farm_articles';
    case ModerateArticles = 'moderate_articles';

    // Farm announcements
    case ManageOwnFarmAnnouncements = 'manage_own_farm_announcements';

    // FAQ bot answers. Super Admin writes the system-wide script; a Content
    // Editor may add or override farmer-seller answers for their own farm.
    case ManageSystemFaq = 'manage_system_faq';
    case ManageOwnFarmFaq = 'manage_own_farm_faq';

    // Descriptive analytics
    case ViewSystemAnalytics = 'view_system_analytics';
    case ViewFarmAnalytics = 'view_farm_analytics';
    case ViewOwnAnalytics = 'view_own_analytics';
    case GenerateExports = 'generate_exports';

    // Data-subject rights for app users (buyers and farmer-sellers)
    case ExportOwnData = 'export_own_data';
    case RequestAccountDeletion = 'request_account_deletion';

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
            self::SetFarmPricing => 'Tighten a farm floor price and maximum discount',
            self::ManageOwnListings => 'Manage own listings',
            self::TakedownListings => 'Take down listings',
            self::ManageOwnTawadRules => 'Manage own tawad rules',
            self::BrowseMarketplace => 'Browse marketplace',
            self::PlaceOrders => 'Place orders',
            self::ManageOwnOrders => 'Manage own orders',
            self::ViewAllOrders => 'View all orders',
            self::RecordWalkInSales => 'Record walk-in sales',
            self::WriteReviews => 'Write reviews',
            self::ModerateReviews => 'Moderate reviews',
            self::SubmitReports => 'Submit reports',
            self::ResolveReports => 'Resolve reports',
            self::ManageOwnFarmArticles => 'Manage own farm crop-care articles',
            self::ModerateArticles => 'Moderate crop-care articles',
            self::ManageOwnFarmAnnouncements => 'Manage own farm announcements',
            self::ManageSystemFaq => 'Manage system-wide FAQ answers',
            self::ManageOwnFarmFaq => 'Manage own farm FAQ answers',
            self::ViewSystemAnalytics => 'View system-wide analytics',
            self::ViewFarmAnalytics => 'View farm analytics',
            self::ViewOwnAnalytics => 'View own analytics',
            self::GenerateExports => 'Generate exports',
            self::ExportOwnData => 'Export own data',
            self::RequestAccountDeletion => 'Request account deletion',
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
            /*
             * Deliberately enumerated rather than self::cases(). The Super
             * Admin governs the system; they do not act inside it. Handing
             * them every case gave them authorship of farm content and
             * ownership of seller pricing, neither of which is theirs.
             *
             * SetFarmPricing is governance, not seller pricing: it tightens a
             * farm's guardrail and never sets a listing price. The Super Admin
             * holds it so a suspended Content Editor cannot leave a farm's
             * numbers unfixable.
             */
            Role::SuperAdmin => [
                self::ManageAccounts,
                self::ApproveFarmerSeller,
                self::SuspendAccounts,
                self::ManageFarms,
                self::ManageCropTypes,
                self::SetCropPricing,
                self::SetFarmPricing,
                self::TakedownListings,
                self::ViewAllOrders,
                self::ModerateReviews,
                self::ResolveReports,
                self::ModerateArticles,
                self::ManageOwnFarmAnnouncements,
                self::ManageSystemFaq,
                self::ViewSystemAnalytics,
                self::ViewFarmAnalytics,
                self::GenerateExports,
            ],

            Role::ContentEditor => [
                self::ManageOwnFarmProfile,
                self::SetFarmPricing,
                self::ViewOwnFarmRoster,
                self::ManageOwnFarmArticles,
                self::ManageOwnFarmAnnouncements,
                self::ManageOwnFarmFaq,
                self::ViewFarmAnalytics,
            ],

            Role::FarmerSeller => [
                self::ManageOwnListings,
                self::ManageOwnTawadRules,
                self::ManageOwnOrders,
                self::RecordWalkInSales,
                self::ViewOwnAnalytics,
                self::SubmitReports,
                self::ExportOwnData,
                self::RequestAccountDeletion,
            ],

            Role::Buyer => [
                self::BrowseMarketplace,
                self::PlaceOrders,
                self::WriteReviews,
                self::SubmitReports,
                self::ExportOwnData,
                self::RequestAccountDeletion,
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
