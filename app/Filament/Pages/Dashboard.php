<?php

namespace App\Filament\Pages;

use App\Actions\Exports\ExportAnalyticsAction;
use App\Actions\Exports\ExportYearlyAnalyticsAction;
use App\Enums\Permission;
use App\Models\Farm;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\Analytics\AnalyticsRange;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Range')
                ->options([
                    'week' => 'This week',
                    'month' => 'This month',
                    'year' => 'This year',
                    'yearly' => 'Yearly',
                    'custom' => 'Custom',
                ])
                ->default('month')
                ->selectablePlaceholder(false)
                ->live()
                ->native(false),
            Select::make('year')
                ->label('Year')
                ->options(fn (Get $get): array => $this->yearOptions($this->chosenFarmId($get('farm_id'))))
                ->default((int) now()->year)
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get): bool => $get('range') === 'yearly')
                ->native(false),
            DatePicker::make('from')
                ->label('From')
                ->maxDate(fn (Get $get): mixed => $get('to') ?: now())
                ->live()
                ->visible(fn (Get $get): bool => $get('range') === 'custom')
                ->native(false),
            DatePicker::make('to')
                ->label('To')
                ->minDate(fn (Get $get): mixed => $get('from'))
                ->maxDate(now())
                ->live()
                ->helperText('Up to 366 days. Longer ranges are shortened.')
                ->visible(fn (Get $get): bool => $get('range') === 'custom')
                ->native(false),
            Select::make('category')
                ->label('Category')
                ->options([
                    'all' => 'All',
                    'fresh' => 'Fresh',
                    'value_added' => 'Value-added',
                ])
                ->default('all')
                ->selectablePlaceholder(false)
                ->visible(fn (): bool => $this->showCategoryFilter())
                ->native(false),
            Select::make('farm_id')
                ->label('Farm')
                ->placeholder('All farms')
                ->options(fn (): array => Farm::query()->orderBy('name')->pluck('name', 'id')->all())
                ->live()
                ->visible(fn (): bool => auth()->user()?->can(Permission::ViewSystemAnalytics->value) ?? false)
                ->native(false),
            TextInput::make('own_farm')
                ->label('Farm')
                ->disabled()
                ->dehydrated(false)
                ->prefixIcon(Heroicon::OutlinedLockClosed)
                ->default(fn (): string => (string) (auth()->user()?->farm?->name ?? ''))
                ->visible(fn (): bool => $this->locksOwnFarm()),
            Text::make(fn (Get $get): string => $this->filterSummary($get))
                ->columnSpanFull(),
        ]);
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 3];
    }

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
            Action::make('yearlyCsv')
                ->label('Yearly CSV')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->modalSubmitActionLabel('Download CSV')
                ->visible(fn (): bool => $this->canDownloadYearly())
                ->schema(fn (): array => $this->yearlyCsvSchema())
                ->action(function (array $data, ExportYearlyAnalyticsAction $export): StreamedResponse {
                    $user = auth()->user();
                    abort_unless($user instanceof User, 403);

                    $farmId = $data['farm_id'] ?? null;
                    $farmId = $farmId === null || $farmId === '' || $farmId === 'all' ? null : (int) $farmId;

                    return $export->download(
                        $user,
                        (int) $data['year'],
                        $farmId,
                        (string) ($data['category'] ?? 'all'),
                    );
                }),
        ];
    }

    /**
     * @return list<Component>
     */
    private function yearlyCsvSchema(): array
    {
        $canChooseFarm = auth()->user()?->can(Permission::GenerateExports->value) ?? false;

        $fields = [
            Select::make('year')
                ->label('Year')
                ->options(function (Get $get) use ($canChooseFarm): array {
                    return $this->yearOptions($canChooseFarm ? $this->chosenFarmId($get('farm_id')) : null);
                })
                ->default((int) now()->year)
                ->required()
                ->selectablePlaceholder(false)
                ->native(false),
        ];

        if ($canChooseFarm) {
            $fields[] = Select::make('farm_id')
                ->label('Farm')
                ->placeholder('All farms')
                ->options(fn (): array => Farm::query()->orderBy('name')->pluck('name', 'id')->all())
                ->live()
                ->native(false);
        }

        if ($canChooseFarm || (auth()->user()?->farm?->allowsValueAdded() ?? false)) {
            $fields[] = Select::make('category')
                ->label('Category')
                ->options([
                    'all' => 'All',
                    'fresh' => 'Fresh',
                    'value_added' => 'Value-added',
                ])
                ->default('all')
                ->selectablePlaceholder(false)
                ->native(false);
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    private function yearOptions(?int $farmId): array
    {
        $years = app(AnalyticsService::class)->availableYears(auth()->user(), $farmId);
        $options = [];

        foreach ($years as $year) {
            $options[$year] = (string) $year;
        }

        return $options;
    }

    private function chosenFarmId(mixed $farmId): ?int
    {
        if (! (auth()->user()?->can(Permission::ViewSystemAnalytics->value) ?? false)) {
            return null;
        }

        if ($farmId === null || $farmId === '' || $farmId === 'all') {
            return null;
        }

        return (int) $farmId;
    }

    /**
     * One line under the filters naming the window, category and farm the
     * widgets below are showing.
     */
    private function filterSummary(Get $get): string
    {
        $year = $get('year');
        $range = AnalyticsRange::fromValues(
            (string) ($get('range') ?? 'month'),
            $this->summaryDate($get('from')),
            $this->summaryDate($get('to')),
            $year === null || $year === '' ? null : (int) $year,
            (string) ($get('category') ?? 'all'),
            true,
        );

        $parts = [$range->from->format('M j').' – '.$range->to->format('M j, Y')];

        if ($this->showCategoryFilter()) {
            $parts[] = match ($range->category) {
                'fresh' => 'Fresh only',
                'value_added' => 'Value-added only',
                default => 'All categories',
            };
        }

        $farm = $this->summaryFarm($get('farm_id'));

        if ($farm !== '') {
            $parts[] = $farm;
        }

        return 'Showing '.implode(' · ', $parts);
    }

    private function summaryFarm(mixed $farmId): string
    {
        if ($this->locksOwnFarm()) {
            return (string) (auth()->user()?->farm?->name ?? '');
        }

        if (! (auth()->user()?->can(Permission::ViewSystemAnalytics->value) ?? false)) {
            return '';
        }

        $chosen = $this->chosenFarmId($farmId);

        if ($chosen === null) {
            return 'All farms';
        }

        return (string) (Farm::query()->whereKey($chosen)->value('name') ?? 'All farms');
    }

    private function summaryDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value, $match) === 1) {
            return $match[0];
        }

        return null;
    }

    private function showCategoryFilter(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->can(Permission::ViewSystemAnalytics->value)) {
            return true;
        }

        return $user->can(Permission::ViewFarmAnalytics->value)
            && ($user->farm?->allowsValueAdded() ?? false);
    }

    private function locksOwnFarm(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can(Permission::ViewFarmAnalytics->value)
            && ! $user->can(Permission::ViewSystemAnalytics->value);
    }

    private function canDownloadYearly(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->can(Permission::GenerateExports->value)) {
            return true;
        }

        return $user->can(Permission::ExportFarmAnalytics->value) && $user->farm_id !== null;
    }
}
