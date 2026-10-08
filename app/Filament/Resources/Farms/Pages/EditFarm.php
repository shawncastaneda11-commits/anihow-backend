<?php

namespace App\Filament\Resources\Farms\Pages;

use App\Filament\Resources\Farms\FarmResource;
use App\Models\Farm;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFarm extends EditRecord
{
    protected static string $resource = FarmResource::class;

    /**
     * @var list<array{label: string, helper: string}>
     */
    private array $featuresTurnedOff = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $farm = $this->getRecord();
        $this->featuresTurnedOff = [];

        if (! $farm instanceof Farm) {
            return;
        }

        $state = $this->form->getState();

        foreach (Farm::featureSwitches() as $column => $switch) {
            $wasOn = (bool) $farm->getAttribute($column);
            $staysOn = (bool) ($state[$column] ?? $wasOn);

            if ($wasOn && ! $staysOn) {
                $this->featuresTurnedOff[] = $switch;
            }
        }
    }

    protected function afterSave(): void
    {
        if ($this->featuresTurnedOff === []) {
            return;
        }

        $names = collect($this->featuresTurnedOff)->pluck('label')->join(', ');
        $effects = collect($this->featuresTurnedOff)
            ->map(fn (array $switch): string => $switch['label'].': '.$switch['helper'])
            ->join("\n");

        Notification::make()
            ->warning()
            ->title($names.' turned off')
            ->body($effects)
            ->send();
    }
}
