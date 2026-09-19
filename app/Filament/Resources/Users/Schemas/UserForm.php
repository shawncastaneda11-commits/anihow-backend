<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as RoleModel;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(30),

                /*
                 * One account, one role. This select is deliberately not
                 * multiple. Super Admin is seeded and cannot be assigned here.
                 */
                Select::make('roles')
                    ->label('Role')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereIn('name', [
                            Role::ContentEditor->value,
                            Role::FarmerSeller->value,
                            Role::Buyer->value,
                        ]),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (RoleModel $record): string => Role::tryFrom($record->name)?->label() ?? $record->name,
                    )
                    ->required()
                    ->native(false)
                    ->preload()
                    ->live()
                    ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false)
                    ->dehydrated(fn (?User $record): bool => ! ($record?->isSuperAdmin() ?? false))
                    ->helperText('One account holds one role. Super Admin is seeded and cannot be assigned from this form.'),

                /*
                 * Required for Content Editors and Farmer-Sellers, meaningless
                 * for Buyers. The schema cannot express that, so it is enforced
                 * here and in validation.
                 */
                Select::make('farm_id')
                    ->label('Farm')
                    ->relationship('farm', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->visible(fn (Get $get): bool => self::isFarmScoped($get))
                    ->required(fn (Get $get): bool => self::isFarmScoped($get))
                    ->helperText('Content Editors and Farmer-Sellers belong to one farm. One Content Editor per farm.'),

                Select::make('status')
                    ->options(UserStatus::options())
                    ->default(UserStatus::Pending->value)
                    ->required()
                    ->native(false)
                    ->helperText('Only active accounts can sign in. Farmer-seller registrations arrive pending.'),

                TextInput::make('location')
                    ->maxLength(255)
                    ->helperText('Barangay and city, for example San Francisco, General Trias, Cavite.'),

                TextInput::make('shop_name')
                    ->label('Storefront name')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => self::hasRole($get, Role::FarmerSeller))
                    ->helperText('Shown to buyers above this seller\'s listings.'),
                Textarea::make('bio')
                    ->rows(3)
                    ->maxLength(2000)
                    ->visible(fn (Get $get): bool => self::hasRole($get, Role::FarmerSeller)),
                TextInput::make('contact')
                    ->maxLength(30)
                    ->visible(fn (Get $get): bool => self::hasRole($get, Role::FarmerSeller))
                    ->helperText('Storefront contact number. Falls back to the account phone if empty.'),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(false),
            ]);
    }

    private static function isFarmScoped(Get $get): bool
    {
        return self::hasRole($get, Role::ContentEditor) || self::hasRole($get, Role::FarmerSeller);
    }

    /**
     * The roles select holds role ids, so match by id rather than by name.
     */
    private static function hasRole(Get $get, Role $role): bool
    {
        $selected = collect((array) $get('roles'))->map(fn ($id): int => (int) $id);

        if ($selected->isEmpty()) {
            return false;
        }

        $roleId = RoleModel::query()->where('name', $role->value)->value('id');

        return $roleId !== null && $selected->contains((int) $roleId);
    }
}
