<?php

namespace App\Actions\Chat;

use App\Models\Order;
use App\Models\StallMessage;
use App\Models\User;

class RemoveOrderChatForUser
{
    /**
     * Hide an order's chat row for this person only, through its latest
     * message. A newer message brings the row back. The other person's view,
     * the messages, and the order stay as they are.
     */
    public function handle(User $user, Order $order): void
    {
        $column = $order->chatClearedColumnFor($user);

        if ($column === null) {
            return;
        }

        $order->forceFill([
            $column => (int) StallMessage::query()->where('order_id', $order->id)->max('id'),
        ])->save();
    }
}
