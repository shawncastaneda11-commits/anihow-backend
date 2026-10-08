<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Enums\OrderStatus;
use App\Enums\StockRemovalReason;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\StockRemoval;
use App\Support\HarvestInput;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class StockHistory
{
    /**
     * @return array{items: LengthAwarePaginator, summary: array<string, mixed>}
     */
    public function forListing(Listing $listing, int $page = 1, int $perPage = 15): array
    {
        $records = $listing->harvestRecords()->orderByDesc('created_at')->orderByDesc('id')->get();
        $removals = $listing->stockRemovals()->orderByDesc('created_at')->orderByDesc('id')->get();

        $rows = $records->map(fn (HarvestRecord $record): array => [
            'type' => 'harvest',
            'id' => $record->id,
            'kind' => $record->kind?->value,
            'harvested_on' => $record->harvested_on?->toDateString(),
            'quantity_harvested' => HarvestInput::scale($record->quantity_harvested),
            'quantity_rejected' => HarvestInput::scale($record->quantity_rejected),
            'quantity_good' => HarvestInput::scale($record->quantity_good),
            'rejection_reason' => $record->rejection_reason?->value,
            'rejection_note' => $record->rejection_note,
            'price_per_unit' => (string) $record->price_per_unit,
            'production_cost' => $record->production_cost === null ? null : HarvestInput::scale($record->production_cost),
            'cost_breakdown' => $record->cost_breakdown,
            'created_at' => $record->created_at?->toIso8601String(),
            'sort_at' => $record->created_at?->getTimestamp() ?? 0,
        ])->concat($removals->map(fn (StockRemoval $removal): array => [
            'type' => 'removal',
            'id' => $removal->id,
            'quantity' => HarvestInput::scale($removal->quantity),
            'reason' => $removal->reason?->value,
            'note' => $removal->note,
            'price_per_unit' => (string) $removal->price_per_unit,
            'created_at' => $removal->created_at?->toIso8601String(),
            'sort_at' => $removal->created_at?->getTimestamp() ?? 0,
        ]))->sortBy([
            ['sort_at', 'desc'],
            ['id', 'desc'],
        ])->values();

        $total = $rows->count();
        $pageItems = $rows->forPage($page, $perPage)->map(function (array $row): array {
            unset($row['sort_at']);

            return $row;
        })->values();

        return [
            'items' => new Paginator(
                $pageItems,
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            ),
            'summary' => $this->summary($listing, $records, $removals),
        ];
    }

    /**
     * @param  Collection<int, HarvestRecord>  $records
     * @param  Collection<int, StockRemoval>  $removals
     * @return array<string, mixed>
     */
    private function summary(Listing $listing, $records, $removals): array
    {
        $harvested = '0.00';
        $rejected = '0.00';
        $good = '0.00';
        $cost = '0.00';
        $anyCost = false;
        $withoutCost = 0;
        $hasEstimated = false;

        foreach ($records as $record) {
            $harvested = bcadd($harvested, HarvestInput::scale($record->quantity_harvested), 2);
            $rejected = bcadd($rejected, HarvestInput::scale($record->quantity_rejected), 2);
            $good = bcadd($good, HarvestInput::scale($record->quantity_good), 2);
            if ($record->production_cost === null) {
                $withoutCost++;
            } else {
                $anyCost = true;
                $cost = bcadd($cost, HarvestInput::scale($record->production_cost), 2);
            }
            if ($record->kind === HarvestRecordKind::Estimated) {
                $hasEstimated = true;
            }
        }

        $removedByReason = [];
        $removed = '0.00';

        foreach (StockRemovalReason::cases() as $reason) {
            $removedByReason[$reason->value] = '0.00';
        }

        foreach ($removals as $removal) {
            $amount = HarvestInput::scale($removal->quantity);
            $removed = bcadd($removed, $amount, 2);
            $key = $removal->reason?->value;
            if ($key !== null) {
                $removedByReason[$key] = bcadd($removedByReason[$key] ?? '0.00', $amount, 2);
            }
        }

        $since = $listing->stock_tracked_since;
        $sold = '0.00';

        if ($since !== null) {
            $sum = OrderItem::query()
                ->where('listing_id', $listing->id)
                ->whereHas('order', function ($query) use ($since): void {
                    $query->whereIn('status', [
                        OrderStatus::Confirmed->value,
                        OrderStatus::Ready->value,
                        OrderStatus::Completed->value,
                    ])->where('confirmed_at', '>=', $since);
                })
                ->sum('quantity');
            $sold = HarvestInput::scale($sum);
        }

        return [
            'tracked_since' => $since?->toIso8601String(),
            'harvested' => $harvested,
            'rejected' => $rejected,
            'good' => $good,
            'sold' => $sold,
            'removed' => $removed,
            'removed_by_reason' => $removedByReason,
            'held' => HarvestInput::scale($listing->quantity_held),
            'available' => HarvestInput::scale($listing->quantity_available),
            'has_estimated' => $hasEstimated,
            'cost_total' => $anyCost ? $cost : null,
            'records_without_cost' => $withoutCost,
        ];
    }
}
