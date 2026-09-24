<?php

namespace App\Filament\Resources\AccountDeletionRequests;

use App\Enums\AccountDeletionStatus;
use App\Enums\Permission;
use App\Filament\Resources\AccountDeletionRequests\Pages\ListAccountDeletionRequests;
use App\Filament\Resources\AccountDeletionRequests\Tables\AccountDeletionRequestsTable;
use App\Models\AccountDeletionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountDeletionRequestResource extends Resource
{
    protected static ?string $model = AccountDeletionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Account deletion requests';

    protected static ?string $modelLabel = 'account deletion request';

    protected static ?string $pluralModelLabel = 'account deletion requests';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return AccountDeletionRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountDeletionRequests::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'processedBy']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageAccounts->value) ?? false;
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

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::query()
            ->where('status', AccountDeletionStatus::Pending)
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
