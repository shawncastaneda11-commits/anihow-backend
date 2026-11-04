<?php

namespace App\Filament\Resources\CropTypes\Schemas;

use App\Actions\Pricing\ChangeCropTypeUnitAction;
use App\Enums\ListingUnit;
use App\Enums\Permission;
use App\Enums\ProductCategory;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Policies\CropTypePolicy;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
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

                Select::make('category')
                    ->label('Category')
                    ->options(ProductCategory::options())
                    ->required()
                    ->native(false)
                    ->default(ProductCategory::FreshProduce->value)
                    ->helperText('Fresh produce is produce as harvested. Value-added is something the farm has processed, such as banana chips.'),

                Select::make('unit_of_measure')
                    ->label('Unit of the floor price')
                    ->options(function (?CropType $record): array {
                        if ($record === null || ! app(ChangeCropTypeUnitAction::class)->isLocked($record)) {
                            return ListingUnit::options();
                        }

                        return collect(ListingUnit::cases())
                            ->filter(fn (ListingUnit $unit): bool => $record->unit_of_measure->convertsTo($unit))
                            ->mapWithKeys(fn (ListingUnit $unit): array => [$unit->value => $unit->label()])
                            ->all();
                    })
                    ->rule(function (?CropType $record): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if (! $record instanceof CropType || $value === null || $value === '') {
                                return;
                            }

                            $action = app(ChangeCropTypeUnitAction::class);
                            $newUnit = ListingUnit::tryFrom((string) $value);

                            if ($newUnit !== null && $action->isLocked($record) && ! $record->unit_of_measure->convertsTo($newUnit)) {
                                $fail($action->refusalMessage($record));
                            }
                        };
                    })
                    ->required()
                    ->native(false)
                    ->default(ListingUnit::Kilogram->value)
                    ->helperText(function (?CropType $record): string {
                        if ($record !== null && app(ChangeCropTypeUnitAction::class)->isLocked($record)) {
                            return 'Listings or a farm price already use this crop, so the unit can only change within the same family. Saving converts the floor price so the real value stays the same.';
                        }

                        return 'This is the unit the floor price is quoted in. It decides which units a seller may use on a listing.';
                    }),

                TextInput::make('floor_price')
                    ->label('Floor price (PHP)')
                    ->numeric()
                    ->step(0.0001)
                    ->rule('decimal:0,4')
                    ->prefix('PHP')
                    ->required()
                    ->minValue(0.0001)
                    ->disabled(fn (?CropType $record): bool => ! self::canSetPricing($record))
                    ->dehydrated(fn (?CropType $record): bool => self::canSetPricing($record))
                    ->helperText('No listing may be priced below this, and no tawad may bring a unit price below it.'),
                TextInput::make('max_discount')
                    ->label('Maximum tawad per unit (PHP, optional)')
                    ->numeric()
                    ->step(0.0001)
                    ->rule('decimal:0,4')
                    ->prefix('PHP')
                    ->nullable()
                    ->minValue(0)
                    ->lt('floor_price')
                    ->placeholder('No limit')
                    ->disabled(fn (?CropType $record): bool => ! self::canSetPricing($record))
                    ->dehydrated(fn (?CropType $record): bool => self::canSetPricing($record))
                    ->helperText('Leave blank for no limit: a seller may give any tawad that keeps the price at or above the floor. '
                        .'If set, it is the most a tawad may take off each unit, and it must stay below the floor. Set 0 to turn tawad off.'),

                self::unitGuardsRepeater(),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive crop types disappear from the marketplace and cannot receive new listings.'),
            ]);
    }

    /**
     * Price limits for the crop's other unit families, such as per bundle for
     * a crop priced per kg. One row per unit family, and never the main
     * unit's family, which the floor and maximum above already cover. A
     * unit's row cannot be switched to another unit: remove it and add a new
     * one, so farm limits set for the old unit are not carried over by
     * mistake.
     */
    private static function unitGuardsRepeater(): Repeater
    {
        return Repeater::make('unitGuards')
            ->label('Other selling units (optional)')
            ->relationship('unitGuards')
            ->schema([
                Select::make('unit')
                    ->label('Unit')
                    ->options(ListingUnit::options())
                    ->required()
                    ->native(false)
                    ->disabled(fn (?CropTypeUnitGuard $record): bool => $record !== null)
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $unit = ListingUnit::tryFrom((string) $value);
                        $mainUnit = ListingUnit::tryFrom((string) $get('../../unit_of_measure'));

                        if ($unit === null) {
                            return;
                        }

                        if ($mainUnit !== null && $mainUnit->convertsTo($unit)) {
                            $fail("Per {$unit->value} is already covered by the floor price above.");

                            return;
                        }

                        $sameFamily = collect($get('../../unitGuards') ?? [])
                            ->pluck('unit')
                            ->map(fn (mixed $other): ?ListingUnit => $other instanceof ListingUnit ? $other : ListingUnit::tryFrom((string) $other))
                            ->filter(fn (?ListingUnit $other): bool => $other !== null && $other->convertsTo($unit))
                            ->count();

                        if ($sameFamily > 1) {
                            $fail("Only one row may cover per {$unit->value}.");
                        }
                    }),
                TextInput::make('floor_price')
                    ->label('Floor price per this unit (PHP)')
                    ->numeric()
                    ->step(0.0001)
                    ->rule('decimal:0,4')
                    ->prefix('PHP')
                    ->required()
                    ->minValue(0.0001),
                TextInput::make('max_discount')
                    ->label('Maximum tawad per this unit (PHP, optional)')
                    ->numeric()
                    ->step(0.0001)
                    ->rule('decimal:0,4')
                    ->prefix('PHP')
                    ->nullable()
                    ->minValue(0)
                    ->lt('floor_price')
                    ->placeholder('No limit'),
            ])
            ->columns(3)
            ->defaultItems(0)
            ->addActionLabel('Add a selling unit')
            ->disabled(fn (?CropType $record): bool => ! self::canSetPricing($record))
            ->columnSpanFull()
            ->helperText('The floor and maximum tawad above cover the main unit and every unit that converts to it. '
                .'Add a row to let sellers use another unit, such as per bundle for a crop priced per kg. '
                .'Bundle, sack, tray, pack and bottle each need their own row.');
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
