<?php

namespace App\Filament\Resources\Farms\RelationManagers;

use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
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

/**
 * A farm's tighten-only price guards, one row per crop type the farm has moved
 * off the system values. Shown on the Farm edit page.
 *
 * Who sees it: anyone holding SetFarmPricing, through the policy's viewAny().
 * Which farm: the Super Admin reaches every farm; a Content Editor only ever
 * opens their own, because FarmResource scopes its query to their farm_id.
 *
 * Every write goes through SetFarmPriceOverrideAction, never a plain Eloquent
 * save, so tighten-only is enforced and newly stranded listings are flagged.
 * The form's min and max values give the user an inline error first; the
 * action is the guarantee.
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
                    ->helperText('One set of guards per crop type. To change one, edit its row.'),

                TextInput::make('floor_price')
                    ->label('Farm floor price (PHP)')
                    ->numeric()
                    ->prefix('PHP')
                    ->nullable()
                    ->requiredWithout('max_discount')
                    ->minValue(fn (Get $get, ?Model $record): ?float => self::systemFloor($get, $record))
                    ->helperText(fn (Get $get, ?Model $record): string => self::systemFloor($get, $record) === null
                        ? 'Choose a crop type first.'
                        : 'The system floor is PHP '.number_format(self::systemFloor($get, $record), 2)
                            .'. The farm may raise it, never lower it. Leave blank to use the system floor.'),

                TextInput::make('max_discount')
                    ->label('Farm maximum tawad (PHP)')
                    ->numeric()
                    ->prefix('PHP')
                    ->nullable()
                    ->requiredWithout('floor_price')
                    ->minValue(0)
                    ->maxValue(fn (Get $get, ?Model $record): ?float => self::systemMaximum($get, $record))
                    ->helperText(fn (Get $get, ?Model $record): string => self::systemMaximum($get, $record) === null
                        ? 'Choose a crop type first.'
                        : 'The system maximum is PHP '.number_format(self::systemMaximum($get, $record), 2)
                            .'. The farm may lower it, never raise it. Leave blank to use the system maximum.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (FarmCropTypeOverride $record): string => $record->cropType?->name ?? 'Crop type')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cropType'))
            ->columns([
                TextColumn::make('cropType.name')
                    ->label('Crop type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('floor_price')
                    ->label('Farm floor')
                    ->money('PHP')
                    ->placeholder('System value'),
                TextColumn::make('cropType.floor_price')
                    ->label('System floor')
                    ->money('PHP')
                    ->color('gray'),
                TextColumn::make('max_discount')
                    ->label('Farm max tawad')
                    ->money('PHP')
                    ->placeholder('System value'),
                TextColumn::make('cropType.max_discount')
                    ->label('System max tawad')
                    ->money('PHP')
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tighten a crop type')
                    ->using(fn (array $data, RelationManager $livewire): Model => self::save($livewire, $data['crop_type_id'], $data)
                        ?? throw new \LogicException('A price guard needs a floor, a maximum tawad, or both.')),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (FarmCropTypeOverride $record, array $data, RelationManager $livewire): Model => self::save($livewire, $record->crop_type_id, $data)
                        ?? $record),

                // Removing the row is the same as saving both sides blank: the
                // farm goes back to the system values. It goes through the
                // action too, so there is one write path.
                DeleteAction::make()
                    ->label('Reset to system')
                    ->modalHeading('Reset to system values')
                    ->modalDescription('This crop type goes back to the system floor and system maximum tawad for this farm.')
                    ->using(function (FarmCropTypeOverride $record, RelationManager $livewire): bool {
                        self::save($livewire, $record->crop_type_id, ['floor_price' => null, 'max_discount' => null]);

                        return true;
                    }),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function save(RelationManager $livewire, int|string $cropTypeId, array $data): ?FarmCropTypeOverride
    {
        /** @var Farm $farm */
        $farm = $livewire->getOwnerRecord();

        // Second guard behind the policy checks Filament already runs. Do not
        // remove one because the other exists.
        abort_unless(
            auth()->user()?->can('manageForFarm', [FarmCropTypeOverride::class, $farm]) ?? false,
            403,
        );

        return app(SetFarmPriceOverrideAction::class)->execute(
            $farm,
            CropType::findOrFail($cropTypeId),
            $data['floor_price'] ?? null,
            $data['max_discount'] ?? null,
        );
    }

    /**
     * Crop types this farm has not tightened yet. On edit, the row's own crop
     * type is included so the disabled select can still show its name.
     *
     * @return array<int|string, string>
     */
    private static function availableCropTypes(RelationManager $livewire, ?Model $record): array
    {
        $taken = $livewire->getOwnerRecord()->cropTypeOverrides()->pluck('crop_type_id');

        return CropType::query()
            ->where(fn (Builder $query) => $query->active()->whereNotIn('id', $taken))
            ->when($record, fn (Builder $query) => $query->orWhere('id', $record->crop_type_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
        $cropType = self::cropType($get, $record);

        return $cropType === null ? null : (float) $cropType->floor_price;
    }

    private static function systemMaximum(Get $get, ?Model $record): ?float
    {
        $cropType = self::cropType($get, $record);

        return $cropType === null ? null : (float) $cropType->max_discount;
    }
}
