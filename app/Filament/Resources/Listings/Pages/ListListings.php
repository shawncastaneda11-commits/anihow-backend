<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use Closure;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    /**
     * No create action. Listings belong to their sellers.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return auth()->user()?->farm_id !== null
            ? 'Help your sellers keep prices, stock and tawad right. Click a listing to edit it; the seller is told what you change.'
            : null;
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

    /**
     * @param  Closure(Builder): Builder  $filter
     */
    private function countFor(Closure $filter): int
    {
        return $filter(ListingResource::getEloquentQuery()->withoutEagerLoads())->count();
    }
}
