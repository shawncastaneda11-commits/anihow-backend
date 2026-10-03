<?php

namespace App\Filament\Resources\CropTypes;

use App\Enums\Permission;
use App\Filament\Resources\CropTypes\Pages\CreateCropType;
use App\Filament\Resources\CropTypes\Pages\EditCropType;
use App\Filament\Resources\CropTypes\Pages\ListCropTypes;
use App\Filament\Resources\CropTypes\Schemas\CropTypeForm;
use App\Filament\Resources\CropTypes\Tables\CropTypesTable;
use App\Models\CropType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Each farm has its own crop types. A Content Editor sees and edits only
 * their farm's rows. The Super Admin sees every farm.
 */
class CropTypeResource extends Resource
{
    protected static ?string $model = CropType::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Crop types';

    protected static ?string $modelLabel = 'crop type';

    protected static ?string $pluralModelLabel = 'crop types';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CropTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CropTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCropTypes::route('/'),
            'create' => CreateCropType::route('/create'),
            'edit' => EditCropType::route('/{record}/edit'),
        ];
    }

    /**
     * CropTypePolicy::viewAny stays open because the API lists crop types for
     * every actor. The panel shows this menu to the Super Admin and to a
     * Content Editor who belongs to a farm.
     */
    public static function canAccess(): bool
    {
        return self::managesCatalog();
    }

    public static function canCreate(): bool
    {
        return self::managesCatalog();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    /**
     * A Content Editor's table is their farm only. The Super Admin sees
     * every farm, including shared rows that no farm has replaced yet.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['farm']);
        $user = auth()->user();

        if ($user === null || $user->can(Permission::ManageCropTypes->value)) {
            return $query;
        }

        return $query->where('farm_id', $user->farm_id);
    }

    private static function managesCatalog(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->can(Permission::ManageCropTypes->value)
            || ($user->can(Permission::ManageOwnFarmProfile->value) && $user->farm_id !== null)
        );
    }
}
