<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function __construct(private readonly InAppNotifier $notifier) {}

    public function handle(
        Reservation $reservation,
        ReservationCancellationReason $reason,
        ?string $note = null,
        bool $notifyBuyer = true,
    ): void {
        DB::transaction(function () use ($reservation, $reason, $note, $notifyBuyer): void {
            $locked = Reservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== ReservationStatus::Active) {
                return;
            }

            $locked->update([
                'status' => ReservationStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancellation_note' => $note,
                'cancelled_at' => now(),
                'active_slot' => null,
            ]);

            if (! $notifyBuyer || $reason === ReservationCancellationReason::BuyerCancelled) {
                return;
            }

            $buyer = $locked->buyer;

            if ($buyer === null || ! $buyer->isActive()) {
                return;
            }

            $this->notifier->reservationCancelled($buyer, $locked);
        });
    }

    public function forListing(Listing $listing, ReservationCancellationReason $reason): void
    {
        Reservation::query()
            ->where('listing_id', $listing->id)
            ->where('status', ReservationStatus::Active)
            ->orderBy('id')
            ->each(fn (Reservation $reservation) => $this->handle($reservation, $reason));
    }

    public function forSeller(User $seller, ReservationCancellationReason $reason, bool $notifyBuyer = true): void
    {
        Reservation::query()
            ->where('farmer_seller_id', $seller->id)
            ->where('status', ReservationStatus::Active)
            ->orderBy('id')
            ->each(fn (Reservation $reservation) => $this->handle($reservation, $reason, null, $notifyBuyer));
    }

    public function forBuyer(User $buyer, ReservationCancellationReason $reason): void
    {
        Reservation::query()
            ->where('buyer_id', $buyer->id)
            ->where('status', ReservationStatus::Active)
            ->orderBy('id')
            ->each(fn (Reservation $reservation) => $this->handle($reservation, $reason, null, false));
    }
}
