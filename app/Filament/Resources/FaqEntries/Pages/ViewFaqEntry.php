<?php

namespace App\Filament\Resources\FaqEntries\Pages;

use App\Filament\Resources\FaqEntries\FaqEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFaqEntry extends ViewRecord
{
    protected static string $resource = FaqEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->getRecord()) ?? false),
        ];
    }
}
