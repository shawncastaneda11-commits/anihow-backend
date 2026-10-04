<?php

namespace App\Filament\Resources\CropTypes\Schemas;

use App\Enums\ListingUnit;
use App\Enums\Permission;
use App\Models\CropType;
use App\Policies\CropTypePolicy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CropTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('farm_id')
                    ->label('Farm')
                    ->relationship('farm', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageCropTypes->value) ?? false)
                    ->helperText('This crop type belongs to one farm. Other farms do not see it.'),

                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule): Unique {
                            $user = auth()->user();

                            if ($user !== null && ! $user->can(Permission::ManageCropTypes->value)) {
                                return $rule->where('farm_id', $user->farm_id);
                            }

                            $farmId = request()->input('data.farm_id');

                            if ($farmId === null || $farmId === '') {
                                return $rule->whereNull('farm_id');
                            }

                            return $rule->where('farm_id', $farmId);
                        },
                    )
                    ->maxLength(120),

                // Bilingual scope is crop labels only. Do not widen this.
                TextInput::make('label_en')
                    ->label('English label')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Tomato'),
                TextInput::make('label_fil')
                    ->label('Filipino label')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Kamatis'),

                Select::make('unit_of_measure')
                    ->label('Unit of the floor price')
                    ->options(ListingUnit::options())
                    ->required()
                    ->native(false)
                    ->default(ListingUnit::Kilogram->value)
                    ->helperText('This is the unit the floor price is quoted in. It decides which units a seller may use on a listing.'),

                TextInput::make('floor_price')
                    ->label('Floor price (PHP)')
                    ->numeric()
                    ->prefix('PHP')
                    ->required()
                    ->minValue(0.01)
                    ->disabled(fn (?CropType $record): bool => ! self::canSetPricing($record))
                    ->dehydrated(fn (?CropType $record): bool => self::canSetPricing($record))
                    ->helperText('No listing may be priced below this, and no tawad may bring a unit price below it.'),
                TextInput::make('max_discount')
                    ->label('Maximum tawad (PHP)')
                    ->numeric()
                    ->prefix('PHP')
                    ->required()
                    ->default(0)
                    ->minValue(0)
                    ->lt('floor_price')
                    ->disabled(fn (?CropType $record): bool => ! self::canSetPricing($record))
                    ->dehydrated(fn (?CropType $record): bool => self::canSetPricing($record))
                    ->helperText('The largest peso discount a seller may set on this crop. Must stay below the floor price.'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive crop types disappear from the marketplace and cannot receive new listings.'),
            ]);
    }

    private static function canSetPricing(?CropType $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return app(CropTypePolicy::class)->setPricing($user, $record);
    }
}
