<?php

namespace App\Actions\Reviews;

use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;

class CreateReviewAction
{
    public function handle(User $buyer, Reservation $reservation, int $rating, ?string $comment = null): Review
    {
        return Review::query()->create([
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $reservation->farmer_seller_id,
            'reservation_id' => $reservation->id,
            'rating' => $rating,
            'comment' => $comment,
        ])->load(['buyer', 'farmerSeller', 'reservation']);
    }
}
