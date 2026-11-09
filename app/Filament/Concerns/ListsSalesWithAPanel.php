<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reservations\ReservationResource;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Orders and Reservations as one screen in two halves: the list with its
 * chips and search, a switch between the two, and the chosen record open
 * read-only in a panel beside the list.
 */
trait ListsSalesWithAPanel
{
    /** The record open in the panel. */
    public ?int $openId = null;

    public string $search = '';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getHeader(): ?View
    {
        return view('filament.blank');
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['anihow-split-page', 'anihow-sales-page'];
    }

    /**
     * The list without Filament's tab bar: the chips render in the row with
     * the search (filament.sales.head).
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openRecord(int|string $key): void
    {
        $record = static::getResource()::getEloquentQuery()->find($key);
        $this->openId = $record instanceof Model ? (int) $record->getKey() : null;
    }

    public function closeRecord(): void
    {
        $this->openId = null;
    }

    public function openedRecord(): ?Model
    {
        return $this->openId === null ? null : static::getResource()::getEloquentQuery()->find($this->openId);
    }

    /**
     * The Orders | Reservations switch.
     *
     * @return list<array{label: string, url: string, on: bool}>
     */
    public function salesSwitch(): array
    {
        return [
            ['label' => 'Orders', 'url' => OrderResource::getUrl(), 'on' => static::getResource() === OrderResource::class],
            ['label' => 'Reservations', 'url' => ReservationResource::getUrl(), 'on' => static::getResource() === ReservationResource::class],
        ];
    }
}
