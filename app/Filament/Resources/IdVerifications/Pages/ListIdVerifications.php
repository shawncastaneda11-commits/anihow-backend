<?php

namespace App\Filament\Resources\IdVerifications\Pages;

use App\Enums\IdVerificationStatus;
use App\Filament\Resources\IdVerifications\IdVerificationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListIdVerifications extends ListRecords
{
    protected static string $resource = IdVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return 'Buyers send an ID before their first order. Click a waiting one to review it.';
    }

    /**
     * The waiting IDs open first.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Waiting')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', IdVerificationStatus::Pending))
                ->badge(fn (): int => IdVerificationResource::getEloquentQuery()->where('status', IdVerificationStatus::Pending)->count()),
            'decided' => Tab::make('Decided')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', IdVerificationStatus::Pending)),
            'all' => Tab::make('All'),
        ];
    }
}
