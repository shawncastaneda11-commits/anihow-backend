<?php

namespace App\Filament\Resources\Farms\RelationManagers;

use App\Enums\Role;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmerCropType;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Each farmer-seller on this farm can have their own crop list. Shown on the
 * farm edit page so the farm's editor can set it without opening each account.
 */
class SellerCropTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'sellerCropTypes';

    protected static ?string $title = 'Crop types';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user !== null
            && $ownerRecord instanceof Farm
            && $user->can('update', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Farmer')
                    ->options(fn (): array => $this->farmers())
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->live(),
                Select::make('crop_type_id')
                    ->label('Crop type')
                    ->options(fn (Get $get): array => $this->availableCropTypes($get('user_id')))
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->helperText('After the first crop is added, that farmer can list only the crops on their list.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (FarmerCropType $record): string => $record->cropType?->name ?? 'Crop type')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['farmer', 'cropType']))
            ->emptyStateHeading('No crop lists yet')
            ->emptyStateDescription('Add a crop for a farmer on this farm. Until a farmer has one, they can use every crop type.')
            ->columns([
                TextColumn::make('farmer.name')
                    ->label('Farmer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cropType.name')
                    ->label('Crop type')
                    ->searchable(),
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

                        return $this->addCropType($data['user_id'], $data['crop_type_id']);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Remove')
                    ->modalDescription('Remove this crop from the farmer\'s list. If their list becomes empty, they can use every crop type again.')
                    ->before(fn () => $this->assertCanWrite()),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function farmers(): array
    {
        /** @var Farm $farm */
        $farm = $this->getOwnerRecord();

        return $farm->farmerSellers()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function availableCropTypes(mixed $userId): array
    {
        /** @var Farm $farm */
        $farm = $this->getOwnerRecord();
        $taken = [];

        if (filled($userId)) {
            $taken = FarmerCropType::query()
                ->where('user_id', $userId)
                ->pluck('crop_type_id')
                ->all();
        }

        return CropType::query()
            ->active()
            ->forFarm($farm->getKey())
            ->when($taken !== [], fn (Builder $query) => $query->whereNotIn('id', $taken))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function addCropType(int|string $userId, int|string $cropTypeId): FarmerCropType
    {
        /** @var Farm $farm */
        $farm = $this->getOwnerRecord();

        $seller = User::query()
            ->whereKey($userId)
            ->where('farm_id', $farm->getKey())
            ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::FarmerSeller->value))
            ->firstOrFail();

        $cropType = CropType::query()
            ->forFarm($farm->getKey())
            ->whereKey($cropTypeId)
            ->firstOrFail();

        return $seller->farmerCropTypes()->create([
            'crop_type_id' => $cropType->id,
        ]);
    }

    private function assertCanWrite(): void
    {
        /** @var Farm $farm */
        $farm = $this->getOwnerRecord();

        abort_unless(auth()->user()?->can('update', $farm) ?? false, 403);
    }
}
