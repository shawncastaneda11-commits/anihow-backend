<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\Permission;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsRange;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turns the dashboard filter form into the same window the phone API uses,
 * clamped so a typed range cannot run past today or past 366 days.
 */
trait UsesDashboardFilters
{
    /** @var array<string, mixed>|null */
    private ?array $dashboardSales = null;

    /** @var array<string, mixed>|null */
    private ?array $dashboardHarvest = null;

    protected function dashboardRange(): AnalyticsRange
    {
        $filters = $this->pageFilters ?? [];
        $year = $filters['year'] ?? null;

        return AnalyticsRange::fromValues(
            (string) ($filters['range'] ?? 'month'),
            $this->filterDate($filters['from'] ?? null),
            $this->filterDate($filters['to'] ?? null),
            $year === null || $year === '' ? null : (int) $year,
            (string) ($filters['category'] ?? 'all'),
            true,
        );
    }

    protected function dashboardFarmId(): ?int
    {
        $user = auth()->user();

        if ($user === null || ! $user->can(Permission::ViewSystemAnalytics->value)) {
            return null;
        }

        $farm = $this->pageFilters['farm_id'] ?? null;

        if ($farm === null || $farm === '' || $farm === 'all') {
            return null;
        }

        return (int) $farm;
    }

    protected function windowLabel(): string
    {
        $range = $this->dashboardRange();

        return $range->from->format('M j').' – '.$range->to->format('M j, Y');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sales(): array
    {
        return $this->dashboardSales ??= app(FarmerSalesAnalytics::class)->build(
            auth()->user(),
            $this->dashboardRange(),
            $this->dashboardFarmId(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function harvest(): array
    {
        return $this->dashboardHarvest ??= app(HarvestAnalytics::class)->build(
            auth()->user(),
            $this->dashboardRange(),
            $this->dashboardFarmId(),
        );
    }

    protected function peso(float $amount): string
    {
        $formatted = '₱'.number_format(abs($amount), 2);

        return $amount < 0 ? '−'.$formatted : $formatted;
    }

    protected function shareText(float $part, float $whole): string
    {
        if ($whole <= 0) {
            return '0%';
        }

        $formatted = number_format(round($part / $whole * 100, 1), 1, '.', '');

        if (str_ends_with($formatted, '.0')) {
            $formatted = substr($formatted, 0, -2);
        }

        return $formatted.'%';
    }

    /**
     * @param  array{start: string, end: string}  $bucket
     */
    protected function periodLabel(array $bucket, string $grouping): string
    {
        $start = Carbon::parse($bucket['start']);
        $end = Carbon::parse($bucket['end']);

        return match ($grouping) {
            'week' => $start->format('M j').'–'.$end->format('M j'),
            'month' => $start->format('M'),
            default => $start->format('M j'),
        };
    }

    private function filterDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) $value;

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text, $match) === 1) {
            return $match[0];
        }

        return $text;
    }
}
