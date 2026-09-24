<?php

namespace App\Support;

use App\Http\Resources\Api\ReviewResource;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopReviews
{
    /**
     * Constraint for withCount / withAvg so a removed review never changes
     * a seller's rating or count.
     *
     * @return array<string, callable>
     */
    public static function receivedAggregate(): array
    {
        return [
            'reviewsReceived' => fn (Builder $query): Builder => $query->visible(),
        ];
    }

    public static function loadStats(User $seller): User
    {
        return $seller
            ->loadCount(self::receivedAggregate())
            ->loadAvg(self::receivedAggregate(), 'rating');
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public static function paginateVisible(User $seller): LengthAwarePaginator
    {
        return $seller->reviewsReceived()
            ->visible()
            ->with('buyer')
            ->latest()
            ->orderByDesc('id')
            ->paginate();
    }

    public static function collection(User $seller): AnonymousResourceCollection
    {
        $reviews = self::paginateVisible($seller);
        self::loadStats($seller);

        return ReviewResource::collection($reviews)->additional([
            'average_rating' => $seller->averageRating(),
            'reviews_count' => (int) ($seller->reviews_received_count ?? 0),
        ]);
    }
}
