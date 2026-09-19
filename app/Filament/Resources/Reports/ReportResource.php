<?php

namespace App\Filament\Resources\Reports;

use App\Enums\Permission;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Tables\ReportsTable;
use App\Models\Report;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $modelLabel = 'report';

    protected static ?string $pluralModelLabel = 'reports';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return ReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['reporter', 'reportable', 'resolvedBy']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ResolveReports->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::query()->where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
