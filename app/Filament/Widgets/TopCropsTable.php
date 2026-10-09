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

class TopCropsTable extends TableWidget
{
    use InteractsWithPageFilters;
    use ScopedAnalytics;
    use UsesDashboardFilters;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top crops')
            ->description(fn (): string => $this->windowLabel())
            ->paginated(false)
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('crop')->label('Crop'),
                TextColumn::make('unit')->label('Unit'),
                TextColumn::make('quantity')->label('Quantity')->alignEnd(),
                TextColumn::make('sales')->label('Sales')->alignEnd(),
                TextColumn::make('share')->label('Share')->alignEnd(),
            ])
            ->emptyStateHeading('No completed sales in this period.')
            ->emptyStateDescription(null)
            ->emptyStateIcon(null);
    }

    /**
     * @return Collection<string, array{crop: string, unit: string, quantity: string, sales: string, share: string}>
     */
    private function rows(): Collection
    {
        $sales = $this->sales();
        $total = (float) $sales['totals']['sales'];

        return collect($sales['top_crops'])
            ->values()
            ->mapWithKeys(function (array $row, int $index) use ($total): array {
                return [
                    (string) ($index + 1) => [
                        'crop' => (string) $row['crop'],
                        'unit' => (string) $row['unit'],
                        'quantity' => HarvestCropNotes::quantity((float) $row['quantity']),
                        'sales' => $this->peso((float) $row['sales']),
                        'share' => $this->shareText((float) $row['sales'], $total),
                    ],
                ];
            });
    }
}
