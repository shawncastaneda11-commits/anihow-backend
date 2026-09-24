<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\User;

class RemoveReviewAction
{
    public function handle(Review $review, User $moderator, string $reason): Review
    {
        $review->update([
            'is_removed' => true,
            'removed_by' => $moderator->id,
            'removed_at' => now(),
            'removal_reason' => $reason,
        ]);

        return $review;
    }
}
