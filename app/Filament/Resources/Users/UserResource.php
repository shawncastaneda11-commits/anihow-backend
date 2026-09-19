<?php

namespace App\Filament\Resources\Users;

use App\Enums\Permission;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Accounts';

    protected static ?string $modelLabel = 'account';

    protected static ?string $pluralModelLabel = 'accounts';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * A Content Editor sees their own farm's roster, read-only. The policy
     * stops them opening another farm's record; this stops the table listing
     * one in the first place. Both are needed.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['roles', 'farm']);
        $user = auth()->user();

        if ($user === null || $user->can(Permission::ManageAccounts->value)) {
            return $query;
        }

        return $query->where('farm_id', $user->farm_id);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageAccounts->value) ?? false;
    }
}
