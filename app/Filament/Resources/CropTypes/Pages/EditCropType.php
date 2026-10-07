<?php

namespace App\Filament\Resources\CropTypes\Pages;

use App\Actions\Pricing\ChangeCropTypeUnitAction;
use App\Filament\Resources\CropTypes\CropTypeResource;
use App\Models\CropType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCropType extends EditRecord
{
    protected static string $resource = CropTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('This removes the crop type and its listings. Past orders keep their item names and prices.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof CropType) {
            return parent::handleRecordUpdate($record, $data);
        }

        return app(ChangeCropTypeUnitAction::class)->update($record, $data);
    }
}
