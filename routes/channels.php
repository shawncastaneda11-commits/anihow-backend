<?php

use App\Models\Order;
use App\Models\StallConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId): bool {
    $order = Order::query()->find($orderId);

    if ($order === null) {
        return false;
    }

    return $user->can('chat', $order);
});

Broadcast::channel('stall-conversations.{conversationId}', function (User $user, int $conversationId): bool {
    $conversation = StallConversation::query()->find($conversationId);

    if ($conversation === null) {
        return false;
    }

    return $user->can('view', $conversation);
});
