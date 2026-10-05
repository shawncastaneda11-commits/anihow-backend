<?php

namespace App\Support\Marketplace;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

class FairMixOrdering
{
    /**
     * Round-robin by seller, rotated once per Asia/Manila day.
     *
     * Filters are already on $query. This only orders that result:
     * 1. Group: listings available now, then upcoming ones (available_from > now,
     *    the same rule as Listing::isUpcoming() once expired rows are already gone).
     * 2. Round: within a group, number each seller's listings 1, 2, 3... newest first.
     * 3. Seller turn: each seller gets a daily number from a hash of the date and
     *    seller id. Adding a seller never moves the others.
     * 4. Final order: group, round, seller turn, listing id. Pagination cannot
     *    repeat or skip a row while the day stays the same.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function apply(Builder $query, ?string $mixDay = null): Builder
    {
        $day = $this->day($mixDay);
        $sellerIds = $this->sellerIds($query);
        $now = now()->toDateTimeString();
        $groupSql = 'CASE WHEN listings.available_from IS NOT NULL AND listings.available_from > ? THEN 1 ELSE 0 END';
        $roundSql = 'ROW_NUMBER() OVER (PARTITION BY CASE WHEN listings.available_from IS NOT NULL AND listings.available_from > ? THEN 1 ELSE 0 END, listings.farmer_seller_id ORDER BY listings.created_at DESC, listings.id DESC)';

        $query
            ->select('listings.*')
            ->selectRaw($groupSql.' as fair_mix_group', [$now])
            ->selectRaw($roundSql.' as fair_mix_round', [$now])
            ->orderBy('fair_mix_group')
            ->orderBy('fair_mix_round');

        if ($sellerIds !== []) {
            $case = 'CASE listings.farmer_seller_id';
            $bindings = [];

            foreach ($this->sellerTurn($sellerIds, $day) as $position => $sellerId) {
                $case .= ' WHEN ? THEN ?';
                $bindings[] = $sellerId;
                $bindings[] = $position;
            }

            $case .= ' ELSE ? END';
            $bindings[] = count($sellerIds);
            $query->orderByRaw($case, $bindings);
        }

        return $query->orderBy('listings.id');
    }

    public function day(?string $mixDay): string
    {
        if (is_string($mixDay) && $mixDay !== '') {
            return $mixDay;
        }

        return now()->timezone('Asia/Manila')->toDateString();
    }

    /**
     * @param  Builder<Listing>  $query
     * @return list<int>
     */
    private function sellerIds(Builder $query): array
    {
        $ids = (clone $query)
            ->reorder()
            ->select('listings.farmer_seller_id')
            ->distinct()
            ->pluck('listings.farmer_seller_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        sort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    /**
     * @param  list<int>  $sortedSellerIds
     * @return list<int>
     */
    private function sellerTurn(array $sortedSellerIds, string $day): array
    {
        usort($sortedSellerIds, function (int $left, int $right) use ($day): int {
            $byTurn = $this->dailyTurn($day, $left) <=> $this->dailyTurn($day, $right);

            return $byTurn !== 0 ? $byTurn : $left <=> $right;
        });

        return array_values($sortedSellerIds);
    }

    private function dailyTurn(string $day, int $sellerId): int
    {
        return crc32('fair-mix:'.$day.':'.$sellerId);
    }
}
