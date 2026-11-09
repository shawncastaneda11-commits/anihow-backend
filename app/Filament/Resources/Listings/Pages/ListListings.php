<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Filament\Resources\Listings\Actions\FarmListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The farm's listings, with the chosen one open in a panel beside the list
 * for its Content Editor: price and stock, tawad, details and dates, and a
 * line saying what the seller will be told. Other offices see the list.
 */
class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    protected static ?string $title = 'Farm listings';

    protected string $view = 'filament.listings.list';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** The listing open in the panel. */
    public ?int $openId = null;

    /** A crop, a title or a seller. */
    public string $search = '';

    /** A Content Editor's seller, or another office's farm. */
    public ?string $owner = null;

    /** @var array<string, mixed>|null */
    public ?array $listing = [];

    private ?Listing $openRecordCache = null;

    /**
     * No create action. Listings belong to their sellers.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return auth()->user()?->farm_id !== null ? 'Farm listings' : 'Listings';
    }

    public function getHeader(): ?View
    {
        return view('filament.blank');
    }

    public function intro(): string
    {
        return auth()->user()?->farm_id !== null
            ? 'Help your sellers keep prices, stock and tawad right. The seller is told what you change.'
            : 'Every farm\'s listings. Take one down if it breaks the rules; the seller is told why.';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['anihow-split-page', 'anihow-listings-page'];
    }

    /**
     * The list without Filament's tab bar: the chips render in the row with
     * the search and the seller (filament.listings.list).
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    /**
     * Quick filters with counts, the same for every office (each sees only
     * the listings it may see).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => ['All', fn (Builder $query): Builder => $query],
            'below' => ['Below the lowest price', fn (Builder $query): Builder => $query->whereHas(
                'cropType',
                fn (Builder $cropType): Builder => $cropType->whereRaw(ListingsTable::belowFloorSql()),
            )],
            'low' => ['Low stock', fn (Builder $query): Builder => $query
                ->where('listings.status', ListingStatus::Published)
                ->where('listings.quantity_available', '<=', ListingsTable::LOW_STOCK)],
            'tawad' => ['With tawad', fn (Builder $query): Builder => $query->whereHas('activeTawadRule')],
            'hidden' => ['Hidden', fn (Builder $query): Builder => $query->where('listings.is_active', false)],
            'soon' => ['Starting soon', fn (Builder $query): Builder => $query->upcoming()],
        ];

        return collect($tabs)
            ->map(fn (array $tab): Tab => Tab::make($tab[0])
                ->modifyQueryUsing($tab[1])
                ->badge(fn (): int => $this->countFor($tab[1])))
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOwner(): void
    {
        $this->resetPage();
    }

    public function ownerLabel(): string
    {
        return auth()->user()?->farm_id !== null ? 'Seller' : 'Farm';
    }

    /**
     * The farm's sellers for its Content Editor, the farms for other offices.
     *
     * @return array<int|string, string>
     */
    public function ownerOptions(): array
    {
        $farmId = auth()->user()?->farm_id;

        if ($farmId === null) {
            return Farm::query()->orderBy('name')->pluck('name', 'id')->all();
        }

        return User::query()
            ->where('farm_id', $farmId)
            ->role(Role::FarmerSeller->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function applyListFilters(Builder $query): Builder
    {
        $term = trim($this->search);
        $column = auth()->user()?->farm_id !== null ? 'listings.farmer_seller_id' : 'listings.farm_id';

        return $query
            ->when($term !== '', fn (Builder $query): Builder => $query->where(fn (Builder $match): Builder => $match
                ->where('listings.title', 'like', "%{$term}%")
                ->orWhereHas('cropType', fn (Builder $crop): Builder => $crop->where('name', 'like', "%{$term}%"))
                ->orWhereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('name', 'like', "%{$term}%"))))
            ->when(filled($this->owner), fn (Builder $query): Builder => $query->where($column, (int) $this->owner));
    }

    // ---- The panel ----

    public function openListing(int|string $key): void
    {
        $record = ListingResource::getEloquentQuery()->find($key);

        if (! $record instanceof Listing || ! FarmListingActions::canEdit($record)) {
            $this->closeListing();

            return;
        }

        $this->openId = (int) $record->getKey();
        $this->openRecordCache = $record;
        $this->resetErrorBag();
        $this->listingForm->model($record)->fill(FarmListingActions::formData($record));
    }

    public function closeListing(): void
    {
        $this->openId = null;
        $this->openRecordCache = null;
        $this->listing = [];
        $this->resetErrorBag();
    }

    public function openRecord(): ?Listing
    {
        if ($this->openId === null) {
            return null;
        }

        if ($this->openRecordCache?->getKey() !== $this->openId) {
            $this->openRecordCache = ListingResource::getEloquentQuery()->find($this->openId);
        }

        return $this->openRecordCache;
    }

    public function listingForm(Schema $schema): Schema
    {
        return $schema
            ->model(fn (): Listing|string => $this->openRecord() ?? Listing::class)
            ->statePath('listing')
            ->components(FarmListingActions::panelSchema());
    }

    public function saveListing(): void
    {
        $record = $this->openRecord();
        abort_unless($record !== null && FarmListingActions::canEdit($record), 403);

        try {
            FarmListingActions::saveFor('listing', $record, $this->listingForm->getState());
        } catch (ValidationException $exception) {
            // The reason also shows under its field, which may be on another tab.
            Notification::make()
                ->title('Not saved.')
                ->body((string) collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            throw $exception;
        }

        Notification::make()->title('Saved. The seller has been told.')->success()->send();
        $this->openListing($record->getKey());
    }

    /**
     * Two letters of the crop, for a listing without a photo.
     */
    public function initials(Listing $listing): string
    {
        return mb_strtoupper(mb_substr((string) ($listing->cropType?->name ?? $listing->title), 0, 2));
    }

    /**
     * @param  Closure(Builder): Builder  $filter
     */
    private function countFor(Closure $filter): int
    {
        return $filter(ListingResource::getEloquentQuery()->withoutEagerLoads())->count();
    }
}
