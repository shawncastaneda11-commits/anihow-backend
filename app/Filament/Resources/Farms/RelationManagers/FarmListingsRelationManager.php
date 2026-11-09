<?php

namespace App\Filament\Resources\Farms\RelationManagers;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The farm's listings at a glance. Editing, and COSEL's takedown, happen on
 * the Listings screen, which this links to.
 */
class FarmListingsRelationManager extends RelationManager
{
    protected static string $relationship = 'listings';

    protected static ?string $title = 'Listings';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ListingResource::canAccess() && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['cropType', 'farm.cropTypeOverrides', 'farmerSeller', 'activeTawadRule']))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->square()
                    ->imageSize(40)
                    ->defaultImageUrl(asset('images/anihow-mark.png')),
                TextColumn::make('title')
                    ->label('Listing')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Listing $record): string => collect([$record->farmerSeller?->name, $record->cropType?->name])->filter()->implode(' · ')),
                TextColumn::make('listing_state')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Listing $record): string => ListingsTable::state($record))
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Shown' => 'success',
                        $state === 'Taken down' => 'danger',
                        str_starts_with($state, 'Starts') => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('price_per_unit')
                    ->label('Price')
                    ->formatStateUsing(fn (Listing $record): string => '₱'.number_format((float) $record->price_per_unit, 2).' / '.($record->unit?->value ?? 'unit'))
                    ->color(fn (Listing $record): ?string => $record->isBelowFloor() ? 'danger' : null)
                    ->description(fn (Listing $record): string => $record->isBelowFloor() ? 'Below the lowest price' : ListingsTable::tawadSummary($record)),
                TextColumn::make('quantity_available')
                    ->label('Stock')
                    ->formatStateUsing(fn (Listing $record): string => rtrim(rtrim(number_format((float) $record->quantity_available, 2), '0'), '.').' '.($record->unit?->value ?? '').' left')
                    ->color('gray'),
            ])
            ->headerActions([
                Action::make('openListings')
                    ->label(fn (): string => auth()->user()?->farm_id !== null ? 'Edit in Listings' : 'Open Listings')
                    ->color('gray')
                    ->url(fn (): string => ListingResource::getUrl()),
            ]);
    }
}
