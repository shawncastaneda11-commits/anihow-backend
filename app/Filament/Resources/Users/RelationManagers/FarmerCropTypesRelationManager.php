<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\Permission;
use App\Models\CropType;
use App\Models\FarmerCropType;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The crop types this farmer-seller may use for new listings. An empty list
 * means every active crop type. Shown on the account edit page.
 */
class FarmerCropTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'farmerCropTypes';

    protected static ?string $title = 'Crop types';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $viewer = auth()->user();

        return $ownerRecord instanceof User
            && $ownerRecord->isFarmerSeller()
            && $viewer !== null
            && (
                $viewer->can(Permission::ManageCropTypes->value)
                || self::editsThisFarm($viewer, $ownerRecord)
            );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('crop_type_id')
                    ->label('Crop type')
                    ->options(fn (): array => $this->availableCropTypes())
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->helperText('After the first crop is added, this farmer can list only the crops on this list.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (FarmerCropType $record): string => $record->cropType?->name ?? 'Crop type')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cropType'))
            ->emptyStateHeading('No crop list yet')
            ->emptyStateDescription('This farmer can use every crop type until you add one. After you add crops, new listings can use only this list.')
            ->columns([
                TextColumn::make('cropType.name')
                    ->label('Crop type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cropType.label_fil')
                    ->label('Filipino'),
                TextColumn::make('cropType.floor_price')
                    ->label('Floor')
                    ->money('PHP'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add crop type')
                    ->using(function (array $data): FarmerCropType {
                        $this->assertCanWrite();

                        /** @var User $farmer */
                        $farmer = $this->getOwnerRecord();
                        $cropType = CropType::query()
                            ->forFarm($farmer->farm_id)
                            ->whereKey($data['crop_type_id'])
                            ->firstOrFail();

                        return $farmer->farmerCropTypes()->create([
                            'crop_type_id' => $cropType->id,
                        ]);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Remove')
                    ->modalDescription('Remove this crop from the farmer\'s list. If the list becomes empty, they can use every crop type again.')
                    ->before(fn () => $this->assertCanWrite()),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function availableCropTypes(): array
    {
        /** @var User $farmer */
        $farmer = $this->getOwnerRecord();
        $taken = $farmer->farmerCropTypes()->pluck('crop_type_id');

        return CropType::query()
            ->active()
            ->forFarm($farmer->farm_id)
            ->whereNotIn('id', $taken)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function assertCanWrite(): void
    {
        $viewer = auth()->user();
        $farmer = $this->getOwnerRecord();

        abort_unless(
            $viewer !== null
            && $farmer instanceof User
            && (
                $viewer->can(Permission::ManageCropTypes->value)
                || self::editsThisFarm($viewer, $farmer)
            ),
            403,
        );
    }

    private static function editsThisFarm(User $viewer, User $farmer): bool
    {
        return $viewer->can(Permission::ManageOwnFarmProfile->value)
            && $viewer->farm_id !== null
            && (int) $viewer->farm_id === (int) $farmer->farm_id;
    }
}
