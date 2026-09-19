<?php

namespace App\Filament\Resources\CropTypes\Schemas;

use App\Enums\ListingUnit;
use App\Enums\Permission;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CropTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
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
                    ->label('Unit of measure')
                    ->options(ListingUnit::options())
                    ->required()
                    ->native(false)
                    ->helperText('Every listing under this crop sells in this unit. Changing it after listings exist will make past figures inconsistent.'),

                /*
                 * Locked to the Super Admin. The disabled state is a second
                 * guard behind CropTypePolicy::setPricing; do not remove one
                 * because the other exists.
                 */
                TextInput::make('floor_price')
                    ->label('Floor price (PHP)')
                    ->numeric()
                    ->prefix('PHP')
                    ->required()
                    ->minValue(0.01)
                    ->disabled(fn (): bool => ! auth()->user()->can(Permission::SetCropPricing->value))
                    ->dehydrated(fn (): bool => auth()->user()->can(Permission::SetCropPricing->value))
                    ->helperText('No listing may be priced below this, and no tawad may bring a unit price below it.'),
                TextInput::make('max_discount')
                    ->label('Maximum tawad (PHP)')
                    ->numeric()
                    ->prefix('PHP')
                    ->required()
                    ->default(0)
                    ->minValue(0)
                    ->lt('floor_price')
                    ->disabled(fn (): bool => ! auth()->user()->can(Permission::SetCropPricing->value))
                    ->dehydrated(fn (): bool => auth()->user()->can(Permission::SetCropPricing->value))
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
}
