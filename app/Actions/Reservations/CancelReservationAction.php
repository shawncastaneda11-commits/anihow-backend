<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationActor;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Support\InAppNotifier;
use App\Support\ListingStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function __construct(
        private ListingStock $stock,
        private InAppNotifier $notifier,
    ) {}

    public function handle(
        Reservation $reservation,
        ReservationActor $actor,
        ?string $reason = null,
    ): Reservation {
        return DB::transaction(function () use ($reservation, $actor, $reason): Reservation {
            $reservation = Reservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ($actor === ReservationActor::Buyer && $reservation->status !== ReservationStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Buyers can only cancel pending reservations.',
                ]);
            }

            $reservation->assertCanTransitionTo(ReservationStatus::Cancelled);

            foreach ($reservation->items as $item) {
                $listing = Listing::query()->whereKey($item->listing_id)->lockForUpdate()->first();

                if ($listing) {
                    $this->stock->restore($listing, (string) $item->quantity);
                }
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor,
                'cancelled_at' => now(),
            ]);

            $reservation = $reservation->refresh()->load(['items.listing', 'buyer', 'farmerSeller']);
            $recipient = $actor === ReservationActor::Buyer
                ? $reservation->farmerSeller
                : $reservation->buyer;

            if ($recipient) {
                $this->notifier->reservationStatusChanged($recipient, $reservation);
            }

            return $reservation;
        });
    }
}
