<?php

namespace App\Filament\Resources\FaqEntries;

use App\Enums\Permission;
use App\Filament\Resources\FaqEntries\Pages\CreateFaqEntry;
use App\Filament\Resources\FaqEntries\Pages\EditFaqEntry;
use App\Filament\Resources\FaqEntries\Pages\ListFaqEntries;
use App\Filament\Resources\FaqEntries\Pages\ViewFaqEntry;
use App\Filament\Resources\FaqEntries\Schemas\FaqEntryForm;
use App\Filament\Resources\FaqEntries\Schemas\FaqEntryInfolist;
use App\Filament\Resources\FaqEntries\Tables\FaqEntriesTable;
use App\Models\FaqEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FaqEntryResource extends Resource
{
    protected static ?string $model = FaqEntry::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'FAQ answers';

    protected static ?string $modelLabel = 'FAQ answer';

    protected static ?string $pluralModelLabel = 'FAQ answers';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return FaqEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FaqEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaqEntriesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->can(Permission::ManageSystemFaq->value)
            || $user->can(Permission::ManageOwnFarmFaq->value)
        );
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->can(Permission::ManageSystemFaq->value)
            || ($user->can(Permission::ManageOwnFarmFaq->value) && $user->farm_id !== null)
        );
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqEntries::route('/'),
            'create' => CreateFaqEntry::route('/create'),
            'view' => ViewFaqEntry::route('/{record}'),
            'edit' => EditFaqEntry::route('/{record}/edit'),
        ];
    }

    /**
     * Super Admin sees every row. A Content Editor sees system-wide rows
     * (read-only) plus their own farm's rows.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['farm']);
        $user = auth()->user();

        if ($user === null || $user->can(Permission::ManageSystemFaq->value)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->whereNull('farm_id')
                ->orWhere('farm_id', $user->farm_id);
        });
    }
}
