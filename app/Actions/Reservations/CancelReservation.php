<?php

namespace App\Actions\Reservations;

use App\Actions\Payments\RecordPaymentEvent;
use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentRejectionReason;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;

class CancelReservation
{
    public function __construct(
        private readonly InAppNotifier $notifier,
        private readonly RecordPaymentEvent $events,
    ) {}

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

            if ($this->settleTrackedPayment($locked)) {
                return;
            }

            if ($reason === ReservationCancellationReason::PaymentExpired) {
                $this->events->forReservation($locked, 'expired');
                $this->notifyPaymentExpired($locked);

                return;
            }

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

    /**
     * A sent or accepted payment becomes a refund. Waiting for payment just cancels.
     */
    private function settleTrackedPayment(Reservation $reservation): bool
    {
        if (! in_array($reservation->payment_status, [
            OrderPaymentStatus::PaymentSent,
            OrderPaymentStatus::Paid,
        ], true)) {
            return false;
        }

        $reservation->payment_status = OrderPaymentStatus::RefundDue;
        $reservation->save();

        PaymentProof::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', PaymentProofStatus::Pending)
            ->update([
                'status' => PaymentProofStatus::Rejected,
                'rejection_reason' => PaymentRejectionReason::Other,
                'rejection_note' => 'Reservation cancelled',
                'reviewed_at' => now(),
            ]);

        $this->events->forReservation($reservation, 'refund_due');

        $reservation->loadMissing(['buyer', 'farmerSeller']);

        foreach ([$reservation->buyer, $reservation->farmerSeller] as $recipient) {
            if ($recipient === null) {
                continue;
            }

            $this->notifier->send(
                $recipient,
                NotificationType::RefundDue,
                NotificationType::RefundDue->label(),
                "Your reservation for {$reservation->listing_name} was cancelled and a refund is due.",
                $reservation,
            );
        }

        return true;
    }

    private function notifyPaymentExpired(Reservation $reservation): void
    {
        $reservation->loadMissing(['buyer', 'farmerSeller']);

        foreach ([$reservation->buyer, $reservation->farmerSeller] as $recipient) {
            if ($recipient === null) {
                continue;
            }

            $this->notifier->send(
                $recipient,
                NotificationType::PaymentExpired,
                NotificationType::PaymentExpired->label(),
                "The payment time for {$reservation->listing_name} has passed.",
                $reservation,
            );
        }
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
