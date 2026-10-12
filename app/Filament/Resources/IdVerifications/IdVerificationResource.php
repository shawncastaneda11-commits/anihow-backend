<?php

namespace App\Filament\Resources\IdVerifications;

use App\Enums\IdVerificationStatus;
use App\Enums\Permission;
use App\Filament\Resources\IdVerifications\Pages\ListIdVerifications;
use App\Filament\Resources\IdVerifications\Tables\IdVerificationsTable;
use App\Models\IdVerification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IdVerificationResource extends Resource
{
    protected static ?string $model = IdVerification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'ID verifications';

    protected static ?string $modelLabel = 'ID verification';

    protected static ?string $pluralModelLabel = 'ID verifications';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return IdVerificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIdVerifications::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'reviewer']);
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
            ->where('status', IdVerificationStatus::Pending)
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
