<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Support\Analytics\HarvestCropNotes;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

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
            ->description(fn (): string => $this->windowLabel())
            ->paginated(false)
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('crop')->label('Crop'),
                TextColumn::make('unit')->label('Unit'),
                TextColumn::make('harvested')->label('Harvested')->alignEnd(),
                TextColumn::make('rejected')->label('Rejected')->alignEnd(),
                TextColumn::make('sold')->label('Sold')->alignEnd(),
                TextColumn::make('waiting')->label('Waiting')->alignEnd(),
                TextColumn::make('removed')->label('Removed')->alignEnd(),
                TextColumn::make('left')->label('Left')->alignEnd(),
                TextColumn::make('expected')->label('Expected')->alignEnd(),
                TextColumn::make('actual')->label('Actual')->alignEnd(),
                TextColumn::make('notes')->label('Notes'),
            ])
            ->emptyStateHeading('No harvests recorded in this period.')
            ->emptyStateDescription(null)
            ->emptyStateIcon(null);
    }

    /**
     * @return Collection<string, array<string, string>>
     */
    private function rows(): Collection
    {
        return collect($this->harvest()['crops'])
            ->values()
            ->mapWithKeys(function (array $crop, int $index): array {
                return [
                    (string) ($index + 1) => [
                        'crop' => (string) $crop['crop'],
                        'unit' => (string) $crop['unit'],
                        'harvested' => HarvestCropNotes::quantity((float) $crop['harvested']),
                        'rejected' => HarvestCropNotes::quantity((float) $crop['rejected']),
                        'sold' => HarvestCropNotes::quantity((float) $crop['sold']),
                        'waiting' => HarvestCropNotes::quantity((float) $crop['waiting']),
                        'removed' => HarvestCropNotes::quantity((float) $crop['removed']),
                        'left' => HarvestCropNotes::quantity((float) $crop['remaining']),
                        'expected' => $this->peso((float) $crop['potential_income']),
                        'actual' => $this->peso((float) $crop['actual_income']),
                        'notes' => HarvestCropNotes::format($crop),
                    ],
                ];
            });
    }
}
