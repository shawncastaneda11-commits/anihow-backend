<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId): bool {
    $order = Order::query()->find($orderId);

    if ($order === null) {
        return false;
    }

    return $user->can('chat', $order);
});
