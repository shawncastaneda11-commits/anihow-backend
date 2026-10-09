<?php

namespace App\Services;

use App\Enums\HarvestRecordKind;
use App\Enums\ListingUnit;
use App\Enums\OrderStatus;
use App\Enums\StockRemovalReason;
use App\Models\HarvestRecord;
use App\Models\OrderItem;
use App\Models\StockRemoval;
use App\Models\User;
use App\Services\Concerns\ScopesAnalytics;
use App\Support\Analytics\AnalyticsRange;
use Illuminate\Database\Eloquent\Builder;

/**
 * Harvest totals for one window, with each in-range record credited for its
 * share of what that listing has sold, is waiting on, and has had removed.
 */
class HarvestAnalytics
{
    use ScopesAnalytics;

    /**
     * @return array<string, mixed>
     */
    public function build(?User $viewer, AnalyticsRange $range, ?int $farmId = null): array
    {
        $query = HarvestRecord::query()
            ->leftJoin('crop_types', 'crop_types.id', '=', 'harvest_records.crop_type_id')
            ->where('harvest_records.kind', '!=', HarvestRecordKind::Opening->value)
            ->whereDate('harvest_records.harvested_on', '>=', $range->from->toDateString())
            ->whereDate('harvest_records.harvested_on', '<=', $range->to->toDateString());

        $this->scopeAnalytics($query, $viewer, 'harvest_records');
        $this->scopeChosenFarm($query, $viewer, $farmId, 'harvest_records');
        $this->applyCategory($query, $range);

        $records = $query->get([
            'harvest_records.*',
            'crop_types.name as crop_name',
        ]);

        $listingIds = $records
            ->pluck('listing_id')
            ->filter(fn (mixed $id): bool => $id !== null)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $inByListing = $this->listingIn($listingIds);
        $movement = $this->listingMovement($listingIds);
        $removals = $this->listingRemovals($listingIds);

        $crops = [];
        $estimated = 0;
        $unlinked = 0;
        $withCost = 0;
        $withoutCost = 0;
        $costTotal = 0.0;
        $potentialWithCost = 0.0;
        $actualWithCost = 0.0;

        foreach ($records as $record) {
            if ($record->kind === HarvestRecordKind::Estimated) {
                $estimated++;
            }

            $good = (float) $record->quantity_good;
            $rejected = (float) $record->quantity_rejected;
            $harvested = (float) $record->quantity_harvested;
            $price = (float) $record->price_per_unit;
            $potential = $good * $price;
            $factor = $this->factor($record->unit);
            $unit = $this->baseUnit($record->unit);
            $listingId = $record->listing_id === null ? null : (int) $record->listing_id;

            $sold = 0.0;
            $waiting = 0.0;
            $actual = 0.0;
            $removedByReason = $this->emptyRemovals();

            if ($listingId === null) {
                $unlinked++;
            } else {
                $lin = $inByListing[$listingId] ?? 0.0;
                $share = $lin > 0 ? $good / $lin : 0.0;
                $moved = $movement[$listingId] ?? ['sold' => 0.0, 'waiting' => 0.0, 'income' => 0.0];
                $sold = $share * $moved['sold'];
                $waiting = $share * $moved['waiting'];
                $actual = $share * $moved['income'];

                foreach ($removals[$listingId] ?? [] as $reason => $quantity) {
                    $removedByReason[$reason] = $share * $quantity;
                }
            }

            $removed = array_sum($removedByReason);
            $cropId = $record->crop_type_id === null ? null : (int) $record->crop_type_id;
            $cropKey = (string) $cropId.'|'.$unit;

            if (! isset($crops[$cropKey])) {
                $crops[$cropKey] = [
                    'crop_type_id' => $cropId,
                    'crop' => (string) ($record->crop_name ?? ''),
                    'unit' => $unit,
                    'harvested' => 0.0,
                    'rejected' => 0.0,
                    'good' => 0.0,
                    'rejected_by_reason' => [],
                    'sold' => 0.0,
                    'waiting' => 0.0,
                    'removed' => 0.0,
                    'removed_by_reason' => $this->emptyRemovals(),
                    'potential_income' => 0.0,
                    'actual_income' => 0.0,
                ];
            }

            $crops[$cropKey]['harvested'] += $harvested * $factor;
            $crops[$cropKey]['rejected'] += $rejected * $factor;
            $crops[$cropKey]['good'] += $good * $factor;
            $crops[$cropKey]['sold'] += $sold * $factor;
            $crops[$cropKey]['waiting'] += $waiting * $factor;
            $crops[$cropKey]['removed'] += $removed * $factor;
            $crops[$cropKey]['potential_income'] += $potential;
            $crops[$cropKey]['actual_income'] += $actual;

            $reason = $record->rejection_reason?->value;
            if ($reason !== null && $rejected > 0) {
                $crops[$cropKey]['rejected_by_reason'][$reason] = ($crops[$cropKey]['rejected_by_reason'][$reason] ?? 0) + ($rejected * $factor);
            }

            foreach ($removedByReason as $removalReason => $quantity) {
                $crops[$cropKey]['removed_by_reason'][$removalReason] += $quantity * $factor;
            }

            if ($record->production_cost === null) {
                $withoutCost++;
            } else {
                $withCost++;
                $costTotal += (float) $record->production_cost;
                $potentialWithCost += $potential;
                $actualWithCost += $actual;
            }
        }

        $cropRows = array_values($crops);
        usort($cropRows, function (array $left, array $right): int {
            $byHarvest = $right['harvested'] <=> $left['harvested'];

            if ($byHarvest !== 0) {
                return $byHarvest;
            }

            return [$left['crop_type_id'] ?? 0, $left['unit']] <=> [$right['crop_type_id'] ?? 0, $right['unit']];
        });

        $cropRows = array_map(function (array $crop): array {
            $rejected = [];
            foreach ($crop['rejected_by_reason'] as $reason => $quantity) {
                $rounded = round($quantity, 2);
                if ($rounded != 0.0) {
                    $rejected[$reason] = $rounded;
                }
            }

            $removedByReason = [];
            foreach ($this->emptyRemovals() as $reason => $zero) {
                $removedByReason[$reason] = round($crop['removed_by_reason'][$reason] ?? $zero, 2);
            }

            $good = round($crop['good'], 2);
            $sold = round($crop['sold'], 2);
            $waiting = round($crop['waiting'], 2);
            $removed = round($crop['removed'], 2);

            return [
                'crop_type_id' => $crop['crop_type_id'],
                'crop' => $crop['crop'],
                'unit' => $crop['unit'],
                'harvested' => round($crop['harvested'], 2),
                'rejected' => round($crop['rejected'], 2),
                'good' => $good,
                'rejected_by_reason' => $rejected === [] ? (object) [] : $rejected,
                'sold' => $sold,
                'waiting' => $waiting,
                'removed' => $removed,
                'removed_by_reason' => $removedByReason,
                'remaining' => max(0, round($good - $sold - $waiting - $removed, 2)),
                'potential_income' => round($crop['potential_income'], 2),
                'actual_income' => round($crop['actual_income'], 2),
            ];
        }, $cropRows);

        $potential = round(array_sum(array_column($cropRows, 'potential_income')), 2);
        $actual = round(array_sum(array_column($cropRows, 'actual_income')), 2);

        return [
            'records' => $records->count(),
            'estimated_records' => $estimated,
            'unlinked_records' => $unlinked,
            'crops' => $cropRows,
            'income' => [
                'potential' => $potential,
                'actual' => $actual,
            ],
            'cost' => $withCost === 0 ? [
                'records_with_cost' => 0,
                'records_without_cost' => $withoutCost,
                'cost_total' => null,
                'potential_income_with_cost' => null,
                'actual_income_with_cost' => null,
                'potential_profit' => null,
                'actual_profit' => null,
            ] : [
                'records_with_cost' => $withCost,
                'records_without_cost' => $withoutCost,
                'cost_total' => round($costTotal, 2),
                'potential_income_with_cost' => round($potentialWithCost, 2),
                'actual_income_with_cost' => round($actualWithCost, 2),
                'potential_profit' => round($potentialWithCost - $costTotal, 2),
                'actual_profit' => round($actualWithCost - $costTotal, 2),
            ],
        ];
    }

    private function applyCategory(Builder $query, AnalyticsRange $range): void
    {
        if ($range->category === 'value_added') {
            $query->where('harvest_records.is_value_added', true);

            return;
        }

        if ($range->category === 'fresh') {
            $query->where('harvest_records.is_value_added', false);
        }
    }

    /**
     * @param  list<int>  $listingIds
     * @return array<int, float>
     */
    private function listingIn(array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        $rows = HarvestRecord::query()
            ->whereIn('listing_id', $listingIds)
            ->groupBy('listing_id')
            ->select('listing_id')
            ->selectRaw(
                'sum(case when kind = ? then quantity_good else 0 end) as opening_good',
                [HarvestRecordKind::Opening->value],
            )
            ->selectRaw(
                'sum(case when kind != ? then quantity_good else 0 end) as harvest_good',
                [HarvestRecordKind::Opening->value],
            )
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->listing_id] = (float) $row->opening_good + (float) $row->harvest_good;
        }

        return $totals;
    }

    /**
     * @param  list<int>  $listingIds
     * @return array<int, array{sold: float, waiting: float, income: float}>
     */
    private function listingMovement(array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('listings', 'listings.id', '=', 'order_items.listing_id')
            ->whereIn('order_items.listing_id', $listingIds)
            ->whereIn('orders.status', [
                OrderStatus::Completed->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Ready->value,
            ])
            ->whereNotNull('listings.stock_tracked_since')
            ->whereRaw('COALESCE(orders.confirmed_at, orders.completed_at) >= listings.stock_tracked_since')
            ->groupBy('order_items.listing_id', 'orders.status')
            ->select('order_items.listing_id', 'orders.status')
            ->selectRaw('sum(order_items.quantity) as qty')
            ->selectRaw('sum(order_items.line_total) as income')
            ->get();

        $movement = [];

        foreach ($rows as $row) {
            $listingId = (int) $row->listing_id;
            $movement[$listingId] ??= ['sold' => 0.0, 'waiting' => 0.0, 'income' => 0.0];
            $status = (string) $row->status;
            $quantity = (float) $row->qty;

            if ($status === OrderStatus::Completed->value) {
                $movement[$listingId]['sold'] += $quantity;
                $movement[$listingId]['income'] += (float) $row->income;
            } else {
                $movement[$listingId]['waiting'] += $quantity;
            }
        }

        return $movement;
    }

    /**
     * @param  list<int>  $listingIds
     * @return array<int, array<string, float>>
     */
    private function listingRemovals(array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        $rows = StockRemoval::query()
            ->whereIn('listing_id', $listingIds)
            ->groupBy('listing_id', 'reason')
            ->select('listing_id', 'reason')
            ->selectRaw('sum(quantity) as qty')
            ->get();

        $removals = [];

        foreach ($rows as $row) {
            $reason = $row->reason instanceof StockRemovalReason ? $row->reason->value : (string) $row->reason;
            $removals[(int) $row->listing_id][$reason] = (float) $row->qty;
        }

        return $removals;
    }

    /**
     * @return array{spoiled: float, damaged: float, sold_outside: float, correction: float}
     */
    private function emptyRemovals(): array
    {
        return [
            StockRemovalReason::Spoiled->value => 0.0,
            StockRemovalReason::Damaged->value => 0.0,
            StockRemovalReason::SoldOutside->value => 0.0,
            StockRemovalReason::Correction->value => 0.0,
        ];
    }

    private function factor(ListingUnit|string|null $unit): float
    {
        $enum = $unit instanceof ListingUnit ? $unit : ListingUnit::tryFrom((string) $unit);

        return $enum?->baseFactor() ?? 1.0;
    }

    private function baseUnit(ListingUnit|string|null $unit): string
    {
        $enum = $unit instanceof ListingUnit ? $unit : ListingUnit::tryFrom((string) $unit);

        return $enum?->baseUnit()->value ?? (string) $unit;
    }
}
