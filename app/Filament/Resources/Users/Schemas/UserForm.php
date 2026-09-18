<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('location')
                    ->maxLength(255)
                    ->helperText('Barangay and city, e.g. San Francisco, General Trias, Cavite. Shown on the shop profile and marketplace.'),
                TextInput::make('shop_name')
                    ->maxLength(255)
                    ->helperText('Farmer-seller stall or farm name shown to buyers.'),
                Textarea::make('bio')
                    ->rows(3)
                    ->maxLength(2000)
                    ->helperText('Short shop description for the buyer shop profile.'),
                TextInput::make('contact')
                    ->maxLength(30)
                    ->helperText('Shop contact number. Falls back to the account phone if empty.'),
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
                Select::make('roles')
                    ->label('Role')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereIn('name', [
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
                    ->default(fn (): ?int => RoleModel::query()
                        ->where('name', Role::FarmerSeller->value)
                        ->value('id'))
                    ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false)
                    ->dehydrated(fn (?User $record): bool => ! ($record?->isSuperAdmin() ?? false))
                    ->helperText('Farmer-seller accounts must be created by super_admin. Buyers may also be created here. Super Admin is seeded and cannot be assigned from this form.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive accounts cannot log in to the mobile app or this panel.'),
            ]);
    }
}
