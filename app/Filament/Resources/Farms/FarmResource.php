<?php

namespace App\Filament\Resources\Farms;

use App\Enums\Permission;
use App\Filament\Resources\Farms\Pages\CreateFarm;
use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Filament\Resources\Farms\Pages\ListFarms;
use App\Filament\Resources\Farms\RelationManagers\CropTypeOverridesRelationManager;
use App\Filament\Resources\Farms\RelationManagers\FarmPhotosRelationManager;
use App\Filament\Resources\Farms\RelationManagers\SellerCropTypesRelationManager;
use App\Filament\Resources\Farms\Schemas\FarmForm;
use App\Filament\Resources\Farms\Tables\FarmsTable;
use App\Models\Farm;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FarmResource extends Resource
{
    protected static ?string $model = Farm::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $navigationLabel = 'Farms';

    protected static ?string $modelLabel = 'farm';

    protected static ?string $pluralModelLabel = 'farms';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return FarmForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmsTable::configure($table);
    }

    /**
     * Price guards, tighten-only. Visible to holders of SetFarmPricing; the
     * farm scope comes from getEloquentQuery() below.
     */
    public static function getRelations(): array
    {
        return [
            'photos' => FarmPhotosRelationManager::class,
            'price-guards' => CropTypeOverridesRelationManager::class,
            'crop-types' => SellerCropTypesRelationManager::class,
        ];
    }

    /**
     * A Content Editor opens their own farm. A Super Admin keeps the farm list.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $user = auth()->user();

        if ($user !== null && ! $user->can(Permission::ManageFarms->value)) {
            return [
                NavigationItem::make('My Farm')
                    ->icon(static::getNavigationIcon())
                    ->group(static::getNavigationGroup())
                    ->sort(static::getNavigationSort())
                    ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.*'))
                    ->url($user->farm_id
                        ? static::getUrl('edit', ['record' => $user->farm_id])
                        : static::getUrl('index')),
            ];
        }

        return parent::getNavigationItems();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarms::route('/'),
            'create' => CreateFarm::route('/create'),
            'edit' => EditFarm::route('/{record}/edit'),
        ];
    }

    /**
     * A Content Editor maintains one farm profile and sees no other.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->can(Permission::ManageFarms->value)) {
            return $query;
        }

        return $query->whereKey($user->farm_id);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageFarms->value) ?? false;
    }
}
