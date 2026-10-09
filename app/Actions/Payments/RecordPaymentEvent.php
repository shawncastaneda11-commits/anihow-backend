<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\OrderPaymentEvent;
use App\Models\Reservation;
use App\Models\User;

class RecordPaymentEvent
{
    public function handle(Order $order, string $event, ?User $actor = null, ?string $note = null): OrderPaymentEvent
    {
        return $this->record($event, $actor, $note, $order->id, null);
    }

    public function forReservation(Reservation $reservation, string $event, ?User $actor = null, ?string $note = null): OrderPaymentEvent
    {
        return $this->record($event, $actor, $note, null, $reservation->id);
    }

    private function record(string $event, ?User $actor, ?string $note, ?int $orderId, ?int $reservationId): OrderPaymentEvent
    {
        return OrderPaymentEvent::query()->create([
            'order_id' => $orderId,
            'reservation_id' => $reservationId,
            'event' => $event,
            'actor_id' => $actor?->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
