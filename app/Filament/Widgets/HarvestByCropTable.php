<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Support\Analytics\HarvestCropNotes;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * What each crop harvested in the period has sold, how much was rejected
 * or removed, and the value of its stock. Highest stock value first.
 */
class HarvestByCropTable extends TableWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Harvest by crop')
            ->description(fn (): string => 'What each crop harvested '.$this->windowLabel().' has sold, and how much was rejected. Highest stock value first.')
            ->paginated(false)
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('crop')->label('Crop')->weight('semibold'),
                TextColumn::make('harvested')->label('Harvested')->alignEnd(),
                TextColumn::make('sold')
                    ->label('Sold')
                    ->alignEnd()
                    ->wrap()
                    ->description(fn (array $record): ?string => $record['waiting']),
                TextColumn::make('rejected')
                    ->label('Rejected')
                    ->alignEnd()
                    ->wrap()
                    ->description(fn (array $record): ?string => $record['removed']),
                ViewColumn::make('sold_percent')
                    ->label('Sold so far')
                    ->view('filament.widgets.columns.sold-share'),
                TextColumn::make('value')
                    ->label('Stock value')
                    ->alignEnd()
                    ->weight('semibold')
                    ->description(fn (array $record): string => $record['earned']),
            ])
            ->emptyStateHeading('No harvests recorded in this period.')
            ->emptyStateDescription(null)
            ->emptyStateIcon(null);
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function rows(): Collection
    {
        return collect($this->harvest()['crops'])
            ->sortByDesc(fn (array $crop): float => (float) $crop['potential_income'])
            ->values()
            ->mapWithKeys(function (array $crop, int $index): array {
                $unit = (string) $crop['unit'];
                $good = (float) $crop['good'];
                $rejected = HarvestCropNotes::rejected($crop);
                $removed = HarvestCropNotes::removed($crop);
                $waiting = (float) $crop['waiting'];

                return [
                    (string) ($index + 1) => [
                        'crop' => (string) $crop['crop'],
                        'harvested' => $this->amount((float) $crop['harvested'], $unit),
                        'sold' => $this->amount((float) $crop['sold'], $unit),
                        'waiting' => $waiting > 0 ? $this->amount($waiting, $unit).' in pending orders' : null,
                        'rejected' => $rejected === [] ? 'None' : implode(', ', $rejected),
                        'removed' => $removed === [] ? null : 'Removed: '.implode(', ', $removed),
                        'sold_percent' => $good > 0 ? min(100.0, round((float) $crop['sold'] / $good * 100)) : 0.0,
                        'value' => $this->peso((float) $crop['potential_income']),
                        'earned' => $this->peso((float) $crop['actual_income']).' earned',
                    ],
                ];
            });
    }

    private function amount(float $quantity, string $unit): string
    {
        return HarvestCropNotes::amount($quantity, $unit);
    }
}
