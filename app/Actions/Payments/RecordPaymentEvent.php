<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\OrderPaymentEvent;
use App\Models\User;

class RecordPaymentEvent
{
    public function handle(Order $order, string $event, ?User $actor = null, ?string $note = null): OrderPaymentEvent
    {
        return $order->paymentEvents()->create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
