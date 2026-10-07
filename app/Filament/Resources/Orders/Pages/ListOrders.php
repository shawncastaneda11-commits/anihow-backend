<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Exports\ExportOrderLedgerAction;
use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->visible(fn (): bool => auth()->user()?->can(Permission::GenerateExports->value) ?? false)
                ->action(function (ExportOrderLedgerAction $export) {
                    return $export->download(
                        auth()->user(),
                        $this->getFilteredTableQuery(),
                        $this->tableFilters ?? [],
                    );
                }),
        ];
    }
}
