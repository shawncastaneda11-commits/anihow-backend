<?php

namespace App\Filament\Resources\Farms\RelationManagers;

use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Enums\ListingUnit;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A farm's tighten-only price guards, one row per crop type and unit the farm
 * has moved off the system values. Shown on the Farm edit page. The unit is
 * blank for the crop's main unit, or one of the crop's extra unit rows.
 *
 * Who sees it: anyone holding SetFarmPricing, through the policy's viewAny().
 * Which farm: the Super Admin reaches every farm; a Content Editor only ever
 * opens their own, because FarmResource scopes its query to their farm_id.
 *
 * Every write goes through SetFarmPriceOverrideAction, never a plain Eloquent
 * save. Tighten-only lives in that action alone, for the panel and for every
 * other caller. This form does not repeat the floor or ceiling bounds.
 */
class CropTypeOverridesRelationManager extends RelationManager
{
    protected static string $relationship = 'cropTypeOverrides';

    protected static ?string $title = 'Price guards';

    /** @var array<int|string, CropType|null> */
    private static array $cropTypes = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('crop_type_id')
                    ->label('Crop type')
                    ->options(fn (RelationManager $livewire, ?Model $record): array => self::availableCropTypes($livewire, $record))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->disabledOn('edit')
                    ->afterStateUpdated(fn (callable $set) => $set('unit', null))
                    ->helperText('One set of guards per crop type and unit. To change one, edit its row.'),

                Select::make('unit')
                    ->label('Unit')
                    ->options(fn (Get $get, RelationManager $livewire, ?Model $record): array => self::availableUnits($get, $livewire, $record))
                    ->placeholder(fn (Get $get, ?Model $record): string => self::mainUnitLabel($get, $record))
                    ->native(false)
                    ->live()
                    ->disabledOn('edit')
                    ->helperText('Blank is the crop\'s main unit. The other choices are the crop\'s extra unit rows.'),

                TextInput::make('floor_price')
                    ->label('Farm floor price (PHP)')
                    ->numeric()
                    ->step(0.0001)
                    ->rules(['nullable', 'decimal:0,4'])
                    ->prefix('PHP')
                    ->nullable()
                    ->requiredWithout('max_discount')
                    ->helperText(fn (Get $get, ?Model $record): string => self::systemFloor($get, $record) === null
                        ? 'Choose a crop type first.'
                        : 'The system floor is PHP '.number_format(self::systemFloor($get, $record), 4)
                            .'. The farm may raise it, never lower it. Leave blank to use the system floor.'),

                TextInput::make('max_discount')
                    ->label('Farm maximum tawad (PHP)')
                    ->numeric()
                    ->step(0.0001)
                    ->rules(['nullable', 'decimal:0,4'])
                    ->prefix('PHP')
                    ->nullable()
                    ->requiredWithout('floor_price')
                    ->helperText(fn (Get $get, ?Model $record): string => match (true) {
                        self::cropType($get, $record) === null => 'Choose a crop type first.',
                        self::systemMaximum($get, $record) === null => 'This crop has no system maximum, so the floor alone limits tawad. '
                            .'The farm may set a maximum per unit. Leave blank for no maximum.',
                        default => 'The system maximum is PHP '.number_format((float) self::systemMaximum($get, $record), 4)
                            .' per unit. The farm may lower it, never raise it. Leave blank to use the system maximum.',
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (FarmCropTypeOverride $record): string => $record->cropType?->name ?? 'Crop type')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cropType.unitGuards'))
            ->columns([
                TextColumn::make('cropType.name')
                    ->label('Crop type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->state(fn (FarmCropTypeOverride $record): ?string => ($record->unit ?? $record->cropType?->unit_of_measure)?->value),
                TextColumn::make('floor_price')
                    ->label('Farm floor')
                    ->money('PHP')
                    ->placeholder('System value'),
                TextColumn::make('system_floor')
                    ->label('System floor')
                    ->state(fn (FarmCropTypeOverride $record): mixed => self::systemSource($record->cropType, $record->unit)?->floor_price)
                    ->money('PHP')
                    ->color('gray'),
                TextColumn::make('max_discount')
                    ->label('Farm max tawad')
                    ->money('PHP')
                    ->placeholder('System value'),
                TextColumn::make('system_max_discount')
                    ->label('System max tawad')
                    ->state(fn (FarmCropTypeOverride $record): mixed => self::systemSource($record->cropType, $record->unit)?->max_discount)
                    ->money('PHP')
                    ->placeholder('No limit')
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tighten a crop type')
                    ->using(fn (array $data, RelationManager $livewire, Action $action): Model => self::save($livewire, $data['crop_type_id'], $data, $action, self::unitFrom($data['unit'] ?? null))
                        ?? throw new \LogicException('A price guard needs a floor, a maximum tawad, or both.')),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (FarmCropTypeOverride $record, array $data, RelationManager $livewire, Action $action): Model => self::save($livewire, $record->crop_type_id, $data, $action, $record->unit)
                        ?? $record),

                // Removing the row is the same as saving both sides blank: the
                // farm goes back to the system values. It goes through the
                // action too, so there is one write path.
                DeleteAction::make()
                    ->label('Reset to system')
                    ->modalHeading('Reset to system values')
                    ->modalDescription('This crop type goes back to the system floor and system maximum tawad for this farm.')
                    ->modalIcon('heroicon-o-arrow-uturn-left')
                    ->modalSubmitActionLabel('Reset')
                    ->successNotificationTitle('Reset to system values')
                    ->using(function (FarmCropTypeOverride $record, RelationManager $livewire): bool {
                        self::save($livewire, $record->crop_type_id, ['floor_price' => null, 'max_discount' => null], null, $record->unit);

                        return true;
                    }),
            ]);
    }

    /**
     * The action reports a bound it refuses under the bare field name. Inside
     * the modal the field lives under the action's state path, so the error is
     * moved there to show under the field instead of being lost.
     *
     * @param  array<string, mixed>  $data
     */
    private static function save(
        RelationManager $livewire,
        int|string $cropTypeId,
        array $data,
        ?Action $action = null,
        ?ListingUnit $unit = null,
    ): ?FarmCropTypeOverride {
        /** @var Farm $farm */
        $farm = $livewire->getOwnerRecord();

        // Second guard behind the policy checks Filament already runs. Do not
        // remove one because the other exists.
        abort_unless(
            auth()->user()?->can('manageForFarm', [FarmCropTypeOverride::class, $farm]) ?? false,
            403,
        );

        try {
            return app(SetFarmPriceOverrideAction::class)->execute(
                $farm,
                CropType::findOrFail($cropTypeId),
                $data['floor_price'] ?? null,
                $data['max_discount'] ?? null,
                $unit,
            );
        } catch (ValidationException $exception) {
            if ($action === null) {
                throw $exception;
            }

            $statePath = 'mountedActions.'.($action->getNestingIndex() ?? 0).'.data.';

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => [$statePath.$field => $messages])
                    ->all(),
            );
        }
    }

    /**
     * Active crop types. A crop with extra unit rows can be tightened once per
     * unit, so a crop that already has a row here stays on the list; the unit
     * choice below leaves out the units already taken. On edit, the row's own
     * crop type is included so the disabled select can still show its name.
     *
     * @return array<int|string, string>
     */
    private static function availableCropTypes(RelationManager $livewire, ?Model $record): array
    {
        return CropType::query()
            ->where(fn (Builder $query) => $query->active())
            ->when($record, fn (Builder $query) => $query->orWhere('id', $record->crop_type_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The crop's extra unit rows this farm has not tightened yet. The main
     * unit is the blank choice.
     *
     * @return array<string, string>
     */
    private static function availableUnits(Get $get, RelationManager $livewire, ?Model $record): array
    {
        $cropType = self::cropType($get, $record);

        if ($cropType === null) {
            return [];
        }

        $taken = $livewire->getOwnerRecord()->cropTypeOverrides()
            ->where('crop_type_id', $cropType->getKey())
            ->whereNotNull('unit')
            ->pluck('unit')
            ->map(fn (mixed $unit): string => $unit instanceof ListingUnit ? $unit->value : (string) $unit)
            ->all();

        return $cropType->unitGuards
            ->filter(fn (CropTypeUnitGuard $row): bool => $record?->unit === $row->unit || ! in_array($row->unit->value, $taken, true))
            ->mapWithKeys(fn (CropTypeUnitGuard $row): array => [$row->unit->value => 'Per '.$row->unit->value])
            ->all();
    }

    private static function mainUnitLabel(Get $get, ?Model $record): string
    {
        $unit = self::cropType($get, $record)?->unit_of_measure;

        return $unit === null ? 'Main unit' : "Main unit (per {$unit->value})";
    }

    private static function unitFrom(mixed $value): ?ListingUnit
    {
        if ($value instanceof ListingUnit) {
            return $value;
        }

        return is_string($value) && $value !== '' ? ListingUnit::tryFrom($value) : null;
    }

    /** The crop's main values, or the extra row's for this unit. */
    private static function systemSource(?CropType $cropType, ?ListingUnit $unit): CropType|CropTypeUnitGuard|null
    {
        if ($cropType === null || $unit === null) {
            return $cropType;
        }

        return $cropType->unitGuards->first(fn (CropTypeUnitGuard $row): bool => $row->unit === $unit);
    }

    private static function cropType(Get $get, ?Model $record): ?CropType
    {
        $id = $get('crop_type_id') ?? $record?->crop_type_id;

        if ($id === null || $id === '') {
            return null;
        }

        return self::$cropTypes[$id] ??= CropType::find($id);
    }

    private static function systemFloor(Get $get, ?Model $record): ?float
    {
        $source = self::systemSource(self::cropType($get, $record), self::unitFrom($get('unit') ?? $record?->unit));

        return $source === null ? null : (float) $source->floor_price;
    }

    private static function systemMaximum(Get $get, ?Model $record): ?float
    {
        $source = self::systemSource(self::cropType($get, $record), self::unitFrom($get('unit') ?? $record?->unit));

        return $source === null || $source->max_discount === null ? null : (float) $source->max_discount;
    }
}
