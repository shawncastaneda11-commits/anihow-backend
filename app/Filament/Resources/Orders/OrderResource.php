<?php

namespace App\Filament\Resources\Orders;

use App\Enums\Permission;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The order ledger, read-only. Status is advanced by the farmer-seller in the
 * app through OrderStateMachine; there is no panel action that moves an order,
 * because a handover happens between two people and the system only records it.
 *
 * Content Editors hold view_farm_analytics but not view_all_orders, so they
 * see aggregate figures and never the ledger.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $modelLabel = 'order';

    protected static ?string $pluralModelLabel = 'orders';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['buyer', 'farmerSeller', 'farm', 'items']);
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
