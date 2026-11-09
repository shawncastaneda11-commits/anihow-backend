<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Analytics\SellerFigures;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * The farm's figures one seller at a time: a row per farmer-seller, and the
 * chosen one's figures in a panel beside the list. Only a farm's Content
 * Editor opens it, for their own farm; it is reached from the Analytics
 * page's "By seller" switch.
 */
class SellerAnalytics extends Page
{
    protected static string $routePath = 'analytics/sellers';

    protected static ?string $title = 'Analytics';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.analytics.sellers';

    public string $period = '30';

    public ?int $openId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->farm_id !== null
            && $user->can(Permission::ViewFarmAnalytics->value)
            && ! $user->can(Permission::ViewSystemAnalytics->value);
    }

    public function getHeader(): ?View
    {
        return view('filament.blank');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['anihow-split-page', 'anihow-seller-analytics-page'];
    }

    public function farmName(): string
    {
        return (string) (auth()->user()?->farm?->name ?? 'Your farm');
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, SellerFigures::PERIODS) ? $period : '30';
    }

    public function openSeller(int $id): void
    {
        $this->openId = $this->sellerOnFarm($id)?->getKey();
    }

    public function closeSeller(): void
    {
        $this->openId = null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return SellerFigures::rows($this->farmId(), $this->period);
    }

    public function openSellerRecord(): ?User
    {
        return $this->openId === null ? null : $this->sellerOnFarm($this->openId);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $seller): array
    {
        return SellerFigures::detail($this->farmId(), $seller, $this->period);
    }

    public function initials(User $user): string
    {
        return collect(preg_split('/\s+/', trim((string) $user->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    private function farmId(): int
    {
        return (int) auth()->user()?->farm_id;
    }

    /**
     * Only a farmer-seller on the viewer's own farm.
     */
    private function sellerOnFarm(int $id): ?User
    {
        return User::query()
            ->whereKey($id)
            ->where('farm_id', $this->farmId())
            ->role(Role::FarmerSeller->value)
            ->withCount(['listings' => fn ($query) => $query->where('is_active', true)])
            ->first();
    }
}
