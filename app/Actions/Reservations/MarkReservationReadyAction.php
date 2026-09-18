<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;

class MarkReservationReadyAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = Reservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservation->assertCanTransitionTo(ReservationStatus::ReadyForPickup);

            $reservation->update([
                'status' => ReservationStatus::ReadyForPickup,
                'ready_at' => now(),
            ]);

            $reservation = $reservation->refresh()->load(['items.listing', 'buyer', 'farmerSeller']);
            $this->notifier->reservationStatusChanged($reservation->buyer, $reservation);

            return $reservation;
        });
    }
}
