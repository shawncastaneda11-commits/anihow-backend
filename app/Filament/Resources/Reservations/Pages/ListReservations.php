<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Concerns\ListsSalesWithAPanel;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Support\Sales\SalesAccess;
use Closure;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reservations, the second half of the Orders screen: the list, and the
 * chosen one open read-only beside it.
 */
class ListReservations extends ListRecords
{
    use ListsSalesWithAPanel;

    protected static string $resource = ReservationResource::class;

    protected string $view = 'filament.sales.reservations';

    public function getTitle(): string
    {
        return 'Orders';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function intro(): string
    {
        return SalesAccess::seesAll(auth()->user())
            ? 'Every farm\'s reservations for listings that open later.'
            : 'Reservations for your farm\'s listings that open later. You can look, not change.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'active' => ['Coming up', fn (Builder $query): Builder => $query->where('reservations.status', ReservationStatus::Active)],
            'converted' => ['Became orders', fn (Builder $query): Builder => $query->where('reservations.status', ReservationStatus::Converted)],
            'cancelled' => ['Cancelled', fn (Builder $query): Builder => $query->where('reservations.status', ReservationStatus::Cancelled)],
            'all' => ['All', fn (Builder $query): Builder => $query],
        ];

        return collect($tabs)
            ->map(fn (array $tab, string $key): Tab => Tab::make($tab[0])
                ->modifyQueryUsing($tab[1])
                ->badge($key === 'active' ? fn (): int => $this->countFor($tab[1]) : null))
            ->all();
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    public function applyListFilters(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $match): Builder => $match
            ->where('reservations.listing_name', 'like', "%{$term}%")
            ->orWhereHas('farmerSeller', fn (Builder $seller): Builder => $seller->where('name', 'like', "%{$term}%")));
    }

    public function openedReservation(): ?Reservation
    {
        $record = $this->openedRecord();

        return $record instanceof Reservation ? $record : null;
    }

    /**
     * @param  Closure(Builder): Builder  $filter
     */
    private function countFor(Closure $filter): int
    {
        return $filter(ReservationResource::getEloquentQuery()->withoutEagerLoads())->count();
    }
}
