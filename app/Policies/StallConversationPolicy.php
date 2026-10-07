<?php

namespace App\Policies;

use App\Models\StallConversation;
use App\Models\User;

/**
 * A buyer starts one thread with a stall. That buyer and that farmer-seller
 * are the only people who can read or write it. It stays after an order ends.
 */
class StallConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBuyer() || $user->isFarmerSeller();
    }

    public function view(User $user, StallConversation $stallConversation): bool
    {
        return $this->isParty($user, $stallConversation);
    }

    public function create(User $user): bool
    {
        return $user->isBuyer();
    }

    public function sendMessage(User $user, StallConversation $stallConversation): bool
    {
        return $this->isParty($user, $stallConversation);
    }

    public function remove(User $user, StallConversation $stallConversation): bool
    {
        return $this->isParty($user, $stallConversation);
    }

    private function isParty(User $user, StallConversation $stallConversation): bool
    {
        return $stallConversation->buyer_id === $user->id
            || $stallConversation->farmer_seller_id === $user->id;
    }
}
