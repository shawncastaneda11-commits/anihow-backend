<?php

namespace App\Support\Dashboard;

use App\Enums\AccountDeletionStatus;
use App\Enums\IdVerificationStatus;
use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\AccountDeletionRequest;
use App\Models\ActivityLog;
use App\Models\CropCareArticle;
use App\Models\FaqEntry;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\IdVerification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Report;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\AppVersion;
use App\Support\System\SystemHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The figures on each office's home screen: what COSEL has waiting, how the
 * system is doing for ICTD, and what a farm's sellers need from its Content
 * Editor. Read-only; every figure links to the screen that acts on it.
 */
final class HomeSummary
{
    /** Stock at or below this many units counts as low on the Content Editor's home. */
    public const LOW_STOCK = 5;

    /**
     * @return array{id_checks: int, deletion_requests: int, open_reports: int, new_accounts: int}
     */
    public static function coselQueue(): array
    {
        return [
            'id_checks' => IdVerification::query()->where('status', IdVerificationStatus::Pending)->count(),
            'deletion_requests' => AccountDeletionRequest::query()->where('status', AccountDeletionStatus::Pending)->count(),
            'open_reports' => Report::query()->where('status', ReportStatus::Open)->count(),
            'new_accounts' => User::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn('name', array_column(Role::adminRoles(), 'value')))
                ->count(),
        ];
    }

    /**
     * Partner farms with their sellers, listings and Content Editor.
     *
     * @return Collection<int, array{farm: Farm, sellers: int, listings: int, editor: ?string}>
     */
    public static function partnerFarms(int $limit = 6): Collection
    {
        return Farm::query()
            ->withCount(['farmerSellers', 'listings'])
            ->with('contentEditor')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Farm $farm): array => [
                'farm' => $farm,
                'sellers' => (int) $farm->farmer_sellers_count,
                'listings' => (int) $farm->listings_count,
                'editor' => $farm->contentEditor?->name,
            ]);
    }

    /**
     * The latest rows of the activity log this person may read.
     *
     * @return Collection<int, ActivityLog>
     */
    public static function recentActivity(User $viewer, int $limit = 5): Collection
    {
        return ActivityLog::query()
            ->visibleTo($viewer)
            ->with('user')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{failed_jobs: int, pending_jobs: int, storage: string, last_backup: ?string, old_apps: int, min_version: ?string, maintenance: bool, two_step_required: bool, admins: int, admins_with_two_step: int}
     */
    public static function ictdOverview(): array
    {
        $minimum = SystemSetting::minimumAppVersion();
        $admins = User::query()->role(array_column(Role::adminRoles(), 'value'));
        $backup = SystemHealth::lastBackupAt();

        return [
            'failed_jobs' => SystemHealth::failedJobs(),
            'pending_jobs' => SystemHealth::pendingJobs(),
            'storage' => SystemHealth::humanBytes(SystemHealth::storageBytes()),
            'last_backup' => $backup?->diffForHumans(),
            'old_apps' => $minimum === null ? 0 : (int) collect(SystemHealth::appVersions())
                ->filter(fn (int $people, int|string $version): bool => AppVersion::isOlder((string) $version, $minimum))
                ->sum(),
            'min_version' => $minimum,
            'maintenance' => SystemSetting::maintenanceMessage() !== null,
            'two_step_required' => SystemSetting::requiresAdminTwoStep(),
            'admins' => (clone $admins)->count(),
            'admins_with_two_step' => (clone $admins)->where('has_email_authentication', true)->count(),
        ];
    }

    /**
     * What the farm's sellers need from its Content Editor.
     *
     * @return array{below_floor: int, low_stock: int, sellers_without_listing: int, listings_shown: int}
     */
    public static function editorQueue(int $farmId): array
    {
        $listings = fn (): Builder => Listing::query()->where('listings.farm_id', $farmId);

        return [
            'below_floor' => $listings()
                ->where('is_active', true)
                ->whereHas('cropType', fn (Builder $cropType): Builder => $cropType->whereRaw(ListingsTable::belowFloorSql()))
                ->count(),
            'low_stock' => $listings()
                ->where('is_active', true)
                ->where('quantity_available', '<=', self::LOW_STOCK)
                ->count(),
            'sellers_without_listing' => User::query()
                ->where('farm_id', $farmId)
                ->role(Role::FarmerSeller->value)
                ->whereDoesntHave('listings', fn (Builder $query): Builder => $query->where('is_active', true))
                ->count(),
            'listings_shown' => $listings()->where('is_active', true)->count(),
        ];
    }

    /**
     * This month's completed sales for the Content Editor's farm.
     *
     * @return array{sales: float, orders: int, top_crop: ?string}
     */
    public static function editorMonth(User $editor): array
    {
        $analytics = app(AnalyticsService::class);
        $since = now()->startOfMonth();
        $summary = $analytics->completedSummary($editor, since: $since, until: now());
        $top = $analytics->bestSelling($editor, limit: 1, since: $since, until: now())->first();

        return [
            'sales' => (float) $summary['gross_sales'],
            'orders' => (int) $summary['completed_orders'],
            'top_crop' => $top?->crop,
        ];
    }

    /**
     * The farm's farmer-sellers, with their shown listings and this week's
     * completed orders.
     *
     * @return Collection<int, array{seller: User, listings: int, orders: int}>
     */
    public static function farmSellers(int $farmId, int $limit = 8): Collection
    {
        $weekOrders = Order::query()
            ->where('farm_id', $farmId)
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(7))
            ->selectRaw('farmer_seller_id, count(*) as orders')
            ->groupBy('farmer_seller_id')
            ->pluck('orders', 'farmer_seller_id');

        return User::query()
            ->where('farm_id', $farmId)
            ->role(Role::FarmerSeller->value)
            ->withCount(['listings' => fn (Builder $query): Builder => $query->where('is_active', true)])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $seller): array => [
                'seller' => $seller,
                'listings' => (int) $seller->listings_count,
                'orders' => (int) ($weekOrders[$seller->id] ?? 0),
            ]);
    }

    /**
     * What the farm last posted to its sellers: announcements, FAQ answers
     * and crop care articles, newest first.
     *
     * @return Collection<int, array{kind: string, title: string, when: string}>
     */
    public static function farmPosts(int $farmId, int $limit = 4): Collection
    {
        $announcements = FarmAnnouncement::query()->where('farm_id', $farmId)->latest()->limit($limit)->get()
            ->map(fn (FarmAnnouncement $item): array => ['kind' => 'Announcement', 'title' => (string) $item->title, 'at' => $item->created_at]);
        $faq = FaqEntry::query()->where('farm_id', $farmId)->latest('updated_at')->limit($limit)->get()
            ->map(fn (FaqEntry $item): array => ['kind' => 'FAQ answer', 'title' => (string) $item->label, 'at' => $item->updated_at]);
        $articles = CropCareArticle::query()->where('farm_id', $farmId)->latest('updated_at')->limit($limit)->get()
            ->map(fn (CropCareArticle $item): array => ['kind' => 'Crop care', 'title' => (string) $item->title, 'at' => $item->updated_at]);

        return $announcements->concat($faq)->concat($articles)
            ->filter(fn (array $item): bool => $item['at'] !== null)
            ->sortByDesc('at')
            ->take($limit)
            ->map(fn (array $item): array => ['kind' => $item['kind'], 'title' => $item['title'], 'when' => $item['at']->diffForHumans()])
            ->values();
    }
}
