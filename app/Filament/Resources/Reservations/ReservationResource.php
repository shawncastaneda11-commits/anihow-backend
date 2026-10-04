<?php

namespace App\Filament\Resources\Reservations;

use App\Enums\Permission;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only reservation ledger for the Super Admin. Content Editors do not
 * hold view_all_orders, so they never see this list.
 */
class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $recordTitleAttribute = 'listing_name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Reservations';

    protected static ?string $modelLabel = 'reservation';

    protected static ?string $pluralModelLabel = 'reservations';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['buyer', 'farmerSeller', 'listing']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewAllOrders->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
