<?php

namespace App\Filament\Resources\ExportLogs;

use App\Enums\Permission;
use App\Filament\Resources\ExportLogs\Pages\ListExportLogs;
use App\Filament\Resources\ExportLogs\Tables\ExportLogsTable;
use App\Models\ExportLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only trail of Super Admin CSV downloads. Nobody edits a log row.
 */
class ExportLogResource extends Resource
{
    protected static ?string $model = ExportLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Export log';

    protected static ?string $modelLabel = 'export';

    protected static ?string $pluralModelLabel = 'exports';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return ExportLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExportLogs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::GenerateExports->value) ?? false;
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
