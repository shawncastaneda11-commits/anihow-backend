<?php

namespace App\Models;

use App\Enums\ExportType;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderStatus;
use App\Enums\ProductCategory;
use Database\Factories\ExportLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'type',
    'filters',
    'row_count',
])]
class ExportLog extends Model
{
    /** @use HasFactory<ExportLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => ExportType::class,
            'filters' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The saved filters in words, for example "All farms · Completed · Any
     * date" or "Year 2026 · PYAP Manggahan Chapter · All categories".
     */
    public function filterSummary(): string
    {
        $filters = $this->filters ?? [];

        $parts = match ($this->type) {
            ExportType::OrderLedger => $this->ledgerParts($filters),
            ExportType::AnalyticsYearly => [
                'Year '.($filters['year'] ?? '—'),
                $this->farmName($filters['farm_id'] ?? null),
                $this->categoryName($filters['category'] ?? null),
            ],
            ExportType::Analytics => [
                $this->dateRange('', $filters['since'] ?? null, $filters['until'] ?? null) ?? 'Any date',
            ],
            default => [],
        };

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function ledgerParts(array $filters): array
    {
        $status = OrderStatus::tryFrom((string) $this->selected($filters, 'status'));
        $fulfillment = FulfillmentPreference::tryFrom((string) $this->selected($filters, 'fulfillment_preference'));
        $placed = $this->dateRange('Placed ', data_get($filters, 'placed_at.from'), data_get($filters, 'placed_at.until'));
        $completed = $this->dateRange('Completed ', data_get($filters, 'completed_at.from'), data_get($filters, 'completed_at.until'));

        return array_values(array_filter([
            $this->farmName($this->selected($filters, 'farm')),
            $status?->label() ?? 'All statuses',
            $placed ?? ($completed === null ? 'Any date' : null),
            $completed,
            $fulfillment?->label(),
        ]));
    }

    /**
     * A select filter is saved as {"value": ...} from the CMS table, or as a
     * plain value when the export is called directly.
     *
     * @param  array<string, mixed>  $filters
     */
    private function selected(array $filters, string $key): mixed
    {
        $value = $filters[$key] ?? null;

        return is_array($value) ? ($value['value'] ?? null) : $value;
    }

    private function farmName(mixed $farmId): string
    {
        if (! is_numeric($farmId)) {
            return 'All farms';
        }

        return Farm::query()->whereKey((int) $farmId)->value('name') ?? 'Farm #'.$farmId;
    }

    private function categoryName(mixed $category): string
    {
        $category = is_string($category) ? ProductCategory::tryFrom($category) : null;

        return $category?->label() ?? 'All categories';
    }

    private function dateRange(string $prefix, mixed $from, mixed $until): ?string
    {
        $from = $this->day($from);
        $until = $this->day($until);

        return match (true) {
            $from !== null && $until !== null => "{$prefix}{$from} – {$until}",
            $from !== null => "{$prefix}from {$from}",
            $until !== null => "{$prefix}until {$until}",
            default => null,
        };
    }

    private function day(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('M j, Y');
    }
}
