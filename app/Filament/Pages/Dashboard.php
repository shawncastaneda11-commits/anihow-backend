<?php

namespace App\Filament\Pages;

use App\Actions\Exports\ExportAnalyticsAction;
use App\Enums\Permission;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->visible(fn (): bool => auth()->user()?->can(Permission::GenerateExports->value) ?? false)
                ->schema([
                    DatePicker::make('from')
                        ->label('From')
                        ->default(now()->startOfDay()->subDays(29)->toDateString())
                        ->required(),
                    DatePicker::make('until')
                        ->label('Until')
                        ->default(now()->toDateString())
                        ->required(),
                ])
                ->action(function (array $data, ExportAnalyticsAction $export) {
                    $since = now()->parse($data['from'])->startOfDay();
                    $until = now()->parse($data['until'])->endOfDay();

                    return $export->download(auth()->user(), $since, $until);
                }),
        ];
    }
}
