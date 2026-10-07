<?php

namespace App\Actions\Chat;

use App\Models\StallConversation;
use App\Models\User;

class RemoveStallConversationForUser
{
    /**
     * Hide the thread for this person only, through the latest message id.
     * The other person's column, messages, and files stay as they are.
     */
    public function handle(User $user, StallConversation $conversation): void
    {
        $column = $conversation->clearedColumnFor($user);

        if ($column === null) {
            return;
        }

        $conversation->forceFill([
            $column => $conversation->messages()->max('id'),
        ])->save();
    }
}
