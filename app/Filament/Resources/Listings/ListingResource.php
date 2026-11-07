<?php

namespace App\Filament\Resources\Listings;

use App\Enums\Permission;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Listings\Schemas\ListingForm;
use App\Filament\Resources\Listings\Tables\ListingsTable;
use App\Models\Listing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * COSEL moderates every listing: takedown and restore, never rewriting a
 * seller's listing. A farm's Content Editor sees that farm's listings only and
 * may edit them, the tawad included (FarmListingActions); each edit is logged
 * and the seller is told. Sellers still manage their own listings in the app.
 */
class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Listings';

    protected static ?string $modelLabel = 'listing';

    protected static ?string $pluralModelLabel = 'listings';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ListingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListings::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['cropType', 'farm.cropTypeOverrides', 'farmerSeller', 'activeTawadRule']);
        $user = auth()->user();

        if ($user !== null && ! $user->can(Permission::TakedownListings->value)) {
            $query->where('listings.farm_id', $user->farm_id ?? 0);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->can(Permission::TakedownListings->value) ?? false)
            || ($user?->can(Permission::ManageFarmListings->value) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
