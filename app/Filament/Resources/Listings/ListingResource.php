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
 * Moderation only. A Super Admin holds takedown power over a published
 * listing, not the power to rewrite a farmer's price, photos or copy, so this
 * resource has no create or edit page. Sellers manage their own listings in
 * the app.
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
        return parent::getEloquentQuery()
            ->with(['cropType', 'farm.cropTypeOverrides', 'farmerSeller', 'activeTawadRule']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::TakedownListings->value) ?? false;
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
