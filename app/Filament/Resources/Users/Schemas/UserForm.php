<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as RoleModel;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        // The form on the left, the account's state in a card beside it.
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make('Details')
                    ->columnSpan(['lg' => 2])
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
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

                        self::roleField(),
                        self::farmField(),

                        /*
                         * Hidden on your own account so an administrator cannot
                         * suspend themselves and lock the panel.
                         */
                        Select::make('status')
                            ->options(UserStatus::options())
                            ->default(UserStatus::Active->value)
                            ->visible(fn (?User $record): bool => self::managesOtherAccount($record))
                            ->required(fn (?User $record): bool => self::managesOtherAccount($record))
                            ->dehydrated(fn (?User $record): bool => self::managesOtherAccount($record))
                            ->native(false)
                            ->helperText('Only active accounts can sign in. Suspend an account to stop it signing in.'),

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
                            ->visible(fn (string $operation, mixed $record): bool => self::showsOwnPassword($operation, $record))
                            ->formatStateUsing(fn (): ?string => null)
                            ->dehydrated(fn (string $operation, mixed $state, mixed $record): bool => self::showsOwnPassword($operation, $record) && filled($state))
                            ->rule(Password::defaults())
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->visible(fn (string $operation, mixed $record): bool => self::showsOwnPassword($operation, $record))
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                    ]),
                Section::make('Account')
                    ->columnSpan(['lg' => 1])
                    ->visible(fn (?User $record): bool => $record?->exists ?? false)
                    ->schema([
                        TextEntry::make('summary_role')
                            ->label('Role')
                            ->state(fn (User $record): string => Role::tryFrom((string) $record->getRoleNames()->first())?->label() ?? '—'),
                        TextEntry::make('summary_joined')
                            ->label('Joined')
                            ->state(fn (User $record): string => $record->created_at?->format('M j, Y') ?? '—'),
                        TextEntry::make('summary_password')
                            ->label('Password')
                            ->state(fn (User $record): string => $record->must_change_password ? 'Still on a temporary password' : 'Set by them'),
                        TextEntry::make('summary_app')
                            ->label('App')
                            ->state(fn (User $record): string => $record->last_app_version
                                ? 'Version '.$record->last_app_version.', '.$record->last_app_seen_at?->diffForHumans()
                                : 'Not seen on 1.0.3 or newer'),
                        TextEntry::make('summary_two_step')
                            ->label('Two-step sign-in')
                            ->state(fn (User $record): string => $record->hasEmailAuthentication() ? 'On' : 'Off'),
                    ]),
            ]);
    }

    /**
     * One account, one role. This select is deliberately not multiple. COSEL
     * assigns people's roles; ICTD assigns the administrator roles
     * (AccountAuthority). Shared by the full form and the account panel.
     */
    public static function roleField(): Select
    {
        return Select::make('roles')
            ->label('Role')
            ->relationship(
                name: 'roles',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => $query->whereIn(
                    'name',
                    array_column(AccountAuthority::assignableRoles(auth()->user()), 'value'),
                ),
            )
            ->getOptionLabelFromRecordUsing(
                fn (RoleModel $record): string => Role::tryFrom($record->name)?->label() ?? $record->name,
            )
            ->required()
            ->native(false)
            ->preload()
            ->live()
            ->visible(fn (?User $record): bool => self::managesOtherAccount($record))
            ->dehydrated(fn (?User $record): bool => self::managesOtherAccount($record))
            ->helperText('One account holds one role.');
    }

    /**
     * Required for Content Editors and Farmer-Sellers, meaningless for Buyers.
     * The schema cannot express that, so it is enforced here and in validation.
     */
    public static function farmField(): Select
    {
        return Select::make('farm_id')
            ->label('Farm')
            ->relationship('farm', 'name')
            ->searchable()
            ->preload()
            ->native(false)
            ->visible(fn (Get $get): bool => self::canManageAccounts() && self::isFarmScoped($get))
            ->required(fn (Get $get): bool => self::canManageAccounts() && self::isFarmScoped($get))
            ->dehydrated(fn (Get $get): bool => self::canManageAccounts() && self::isFarmScoped($get))
            ->rule(fn (Get $get, Select $component): Closure => self::oneContentEditorPerFarm($get, $component))
            ->helperText('Content Editors and Farmer-Sellers belong to one farm. One Content Editor per farm.');
    }

    private static function showsOwnPassword(string $operation, mixed $record): bool
    {
        return $operation === 'edit'
            && $record instanceof User
            && $record->exists
            && auth()->id() === $record->getKey();
    }

    private static function managesOtherAccount(?User $record): bool
    {
        $actor = auth()->user();

        // An account this request just created has no role yet, so it counts
        // as new: otherwise ICTD's choice of an administrator role is dropped.
        return $record === null || ! $record->exists || $record->wasRecentlyCreated
            ? AccountAuthority::managesAny($actor)
            : AccountAuthority::canManageOther($actor, $record);
    }

    private static function canManageAccounts(): bool
    {
        return AccountAuthority::managesPeople(auth()->user());
    }

    /**
     * A farm has one Content Editor. Farmer-sellers on that farm are unaffected.
     */
    private static function oneContentEditorPerFarm(Get $get, Select $component): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $component): void {
            if (blank($value) || ! self::hasRole($get, Role::ContentEditor)) {
                return;
            }

            $record = $component->getRecord();
            $recordId = ($record !== null && $record->exists) ? $record->getKey() : null;

            $farmAlreadyHasEditor = User::query()
                ->where('farm_id', $value)
                ->when(
                    $recordId !== null,
                    fn (Builder $query): Builder => $query->whereKeyNot($recordId),
                )
                ->role(Role::ContentEditor->value)
                ->exists();

            if ($farmAlreadyHasEditor) {
                $fail('This farm already has a Content Editor.');
            }
        };
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
