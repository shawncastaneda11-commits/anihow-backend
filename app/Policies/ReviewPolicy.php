<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Review $review): bool
    {
        if ($user->can(Permission::ModerateReviews->value)) {
            return true;
        }

        if ($review->isOwnedBy($user)) {
            return true;
        }

        // A removed review is invisible to everyone except its author and the
        // moderator. Your previous policy showed it to every authenticated user.
        return ! $review->is_removed;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WriteReviews->value);
    }

    /**
     * A review unlocks at Completed, one per order, and only for the buyer on
     * that order. Call this with the order, not the review.
     */
    public function createForOrder(User $user, Order $order): bool
    {
        return $user->can(Permission::WriteReviews->value)
            && $order->isOwnedByBuyer($user)
            && $order->canBeReviewed();
    }

    /**
     * Reviews are not editable. A review is tied to a completed transaction and
     * rewriting it after the fact would make the rating meaningless.
     */
    public function update(User $user, Review $review): bool
    {
        return false;
    }

    public function remove(User $user, Review $review): bool
    {
        return $user->can(Permission::ModerateReviews->value);
    }
}
