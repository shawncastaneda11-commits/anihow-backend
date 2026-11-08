<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\Permission;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only record of every change made in the CMS by an administrator or a
 * Content Editor. COSEL sees people's rows, ICTD sees administrator rows,
 * and both see system rows (ActivityLog::scopeVisibleTo). Nobody edits or
 * deletes a row.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Records';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');
        $viewer = auth()->user();

        return $viewer instanceof User ? $query->visibleTo($viewer) : $query->whereRaw('1 = 0');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewActivityLog->value) ?? false;
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
