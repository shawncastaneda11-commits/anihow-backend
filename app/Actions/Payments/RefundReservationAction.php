<?php

namespace App\Actions\Payments;

use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Support\InAppNotifier;
use App\Support\PaymentReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundReservationAction
{
    public function __construct(
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
    ) {}

    public function handle(User $seller, Reservation $reservation, string $reference): Reservation
    {
        return DB::transaction(function () use ($seller, $reservation, $reference): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ((int) $reservation->farmer_seller_id !== (int) $seller->id) {
                abort(403);
            }

            if ($reservation->payment_status !== OrderPaymentStatus::RefundDue) {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation does not have a refund due.',
                ]);
            }

            $normalized = PaymentReference::normalize($reference);

            if (! preg_match('/^[A-Z0-9]{6,40}$/', $normalized)) {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Enter 6 to 40 letters or digits.',
                ]);
            }

            $reservation->forceFill([
                'payment_status' => OrderPaymentStatus::Refunded,
                'refund_reference' => $normalized,
                'refunded_at' => now(),
                'refunded_by' => $seller->id,
            ])->save();

            $this->events->forReservation($reservation, 'refunded', $seller, $normalized);

            $reservation->loadMissing('buyer');

            if ($reservation->buyer !== null) {
                $this->notifier->send(
                    $reservation->buyer,
                    NotificationType::RefundCompleted,
                    NotificationType::RefundCompleted->label(),
                    "Your refund for {$reservation->listing_name} was sent. Reference {$normalized}.",
                    $reservation,
                );
            }

            return $reservation->refresh();
        });
    }
}
