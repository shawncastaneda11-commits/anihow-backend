<?php

namespace App\Filament\Pages;

use App\Actions\Exports\ExportAnalyticsAction;
use App\Actions\Exports\ExportYearlyAnalyticsAction;
use App\Enums\Permission;
use App\Models\Farm;
use App\Models\User;
use App\Services\AnalyticsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
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
                ->visible(fn (Get $get): bool => $get('range') === 'custom')
                ->native(false),
            DatePicker::make('to')
                ->label('To')
                ->maxDate(now())
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
        ]);
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
