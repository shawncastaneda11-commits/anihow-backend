<?php

namespace App\Filament\Widgets\Home;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\FaqEntries\FaqEntryResource;
use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\User;
use App\Support\Dashboard\HomeSummary;
use Filament\Widgets\Widget;

/**
 * A Content Editor's home screen: what the farm's sellers need, this month's sales, and what was posted.
 */
class EditorHome extends Widget
{
    protected static bool $isDiscovered = false;

    /** The home screen shows at once instead of loading after the page. */
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.home.editor';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isContentEditor() && $user->farm_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $editor */
        $editor = auth()->user();
        $farmId = (int) $editor->farm_id;
        $queue = HomeSummary::editorQueue($farmId);
        $listings = ListingResource::getUrl();

        return [
            'farmName' => $editor->farm?->name,
            'tiles' => [
                ['label' => 'Below the lowest price', 'count' => $queue['below_floor'], 'cta' => 'Fix the price', 'url' => $listings, 'warn' => $queue['below_floor'] > 0],
                ['label' => 'Low stock ('.HomeSummary::LOW_STOCK.' or less)', 'count' => $queue['low_stock'], 'cta' => 'See listings', 'url' => $listings, 'warn' => $queue['low_stock'] > 0],
                ['label' => 'Sellers with no listing', 'count' => $queue['sellers_without_listing'], 'cta' => 'Help them start', 'url' => $listings, 'warn' => $queue['sellers_without_listing'] > 0],
                ['label' => 'Listings shown to buyers', 'count' => $queue['listings_shown'], 'cta' => 'See all', 'url' => $listings, 'warn' => false],
            ],
            'month' => HomeSummary::editorMonth($editor),
            'analyticsUrl' => Dashboard::getUrl(),
            'sellers' => HomeSummary::farmSellers($farmId),
            'posts' => HomeSummary::farmPosts($farmId),
            'announceUrl' => FarmAnnouncementResource::getUrl('index'),
            'faqUrl' => FaqEntryResource::getUrl('index'),
            'listingsUrl' => $listings,
        ];
    }
}
