<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopedAnalytics;
use App\Services\AnalyticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Best-selling produce. One Filament table with a week/month filter matching
 * the Units-sold chart dropdown. Figures still come from AnalyticsService —
 * this widget only presents them.
 */
class BestSellingTable extends TableWidget
{
    use ScopedAnalytics;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /**
     * Chart-style period filter. Default matches the previous "This week" block.
     */
    public ?string $filter = 'week';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.best-selling-table';

    /**
     * @return array<string, string>
     */
    protected function getFilters(): array
    {
        return [
            'week' => 'This week',
            'month' => 'This month',
        ];
    }

    public function updatedFilter(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->paginated(false)
            ->records(fn (): Collection => $this->records())
            ->columns([
                TextColumn::make('rank')
                    ->label('Rank')
                    ->alignStart(),
                TextColumn::make('crop')
                    ->label('Crop'),
                TextColumn::make('units')
                    ->label('Units')
                    ->alignEnd()
                    ->state(function (array $record): string {
                        return number_format((float) $record['units'], 2).' '.$record['unit_of_measure'];
                    }),
                TextColumn::make('revenue')
                    ->label('Sales')
                    ->alignEnd()
                    ->state(function (array $record): string {
                        return '₱'.number_format((float) $record['revenue'], 2);
                    }),
            ])
            ->emptyStateHeading('No completed sales in this period.')
            ->emptyStateDescription(null)
            ->emptyStateIcon(null);
    }

    /**
     * Rank is presentation-only. Units and revenue stay as AnalyticsService returned them.
     *
     * @return Collection<int|string, array{rank: int, crop: string, units: mixed, unit_of_measure: string, revenue: mixed}>
     */
    protected function records(): Collection
    {
        $window = $this->filter === 'month' ? 'month' : 'week';

        return app(AnalyticsService::class)
            ->bestSelling(auth()->user(), $window)
            ->values()
            ->mapWithKeys(function (object $row, int $index): array {
                $rank = $index + 1;

                return [
                    $rank => [
                        'rank' => $rank,
                        'crop' => (string) $row->crop,
                        'units' => $row->units,
                        'unit_of_measure' => (string) $row->unit_of_measure,
                        'revenue' => $row->revenue,
                    ],
                ];
            });
    }
}
