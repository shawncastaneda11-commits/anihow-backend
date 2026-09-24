<?php

namespace App\Filament\Resources\FarmAnnouncements;

use App\Enums\Permission;
use App\Filament\Resources\FarmAnnouncements\Pages\CreateFarmAnnouncement;
use App\Filament\Resources\FarmAnnouncements\Pages\EditFarmAnnouncement;
use App\Filament\Resources\FarmAnnouncements\Pages\ListFarmAnnouncements;
use App\Filament\Resources\FarmAnnouncements\Schemas\FarmAnnouncementForm;
use App\Filament\Resources\FarmAnnouncements\Tables\FarmAnnouncementsTable;
use App\Models\FarmAnnouncement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FarmAnnouncementResource extends Resource
{
    protected static ?string $model = FarmAnnouncement::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Announcements';

    protected static ?string $modelLabel = 'announcement';

    protected static ?string $pluralModelLabel = 'announcements';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return FarmAnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmAnnouncementsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageOwnFarmAnnouncements->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageOwnFarmAnnouncements->value) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarmAnnouncements::route('/'),
            'create' => CreateFarmAnnouncement::route('/create'),
            'edit' => EditFarmAnnouncement::route('/{record}/edit'),
        ];
    }

    /**
     * Farm scope. The policy stops a Content Editor opening another farm's
     * announcement; this stops the table listing one.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['farm', 'author']);
        $user = auth()->user();

        if ($user === null || $user->can(Permission::ManageFarms->value)) {
            return $query;
        }

        return $query->where('farm_id', $user->farm_id);
    }
}
