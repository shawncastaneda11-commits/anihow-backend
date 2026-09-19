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

/**
 * The shared crop taxonomy. System-wide, Super Admin owned. Content Editors
 * do not see this resource at all: one kamatis entry, one floor price, many
 * listings, and a per-farm floor would not be a guardrail.
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
     * CropTypePolicy::viewAny is true for everyone because every actor reads
     * the taxonomy through the API. The panel is narrower.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageCropTypes->value) ?? false;
    }
}
