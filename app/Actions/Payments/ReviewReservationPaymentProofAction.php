<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\OpenDueReservations;
use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentRejectionReason;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewReservationPaymentProofAction
{
    public function __construct(
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
        private PostPaymentChatNote $chat,
        private OpenDueReservations $openDue,
    ) {}

    public function handle(
        User $seller,
        Reservation $reservation,
        PaymentProof $proof,
        string $decision,
        ?PaymentRejectionReason $reason = null,
        ?string $note = null,
    ): Reservation {
        return DB::transaction(function () use ($seller, $reservation, $proof, $decision, $reason, $note): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $proof = PaymentProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();

            if ((int) $reservation->farmer_seller_id !== (int) $seller->id || (int) $proof->reservation_id !== (int) $reservation->id) {
                abort(403);
            }

            if ($proof->status !== PaymentProofStatus::Pending) {
                throw ValidationException::withMessages([
                    'proof' => 'This payment was already reviewed.',
                ]);
            }

            $reservation->loadMissing(['buyer', 'listing']);

            if ($decision === 'accept') {
                $proof->forceFill([
                    'status' => PaymentProofStatus::Accepted,
                    'reviewed_by' => $seller->id,
                    'reviewed_at' => now(),
                ])->save();

                $reservation->payment_status = OrderPaymentStatus::Paid;
                $reservation->paid_at = now();
                $reservation->save();

                $this->events->forReservation($reservation, 'proof_accepted', $seller);

                if ($reservation->buyer !== null) {
                    $this->notifier->send(
                        $reservation->buyer,
                        NotificationType::PaymentConfirmed,
                        NotificationType::PaymentConfirmed->label(),
                        "Your payment for {$reservation->listing_name} was confirmed.",
                        $reservation,
                    );
                }

                $this->chat->forReservation($seller, $reservation, 'Payment received. Thank you!');

                $listingId = $reservation->listing_id;

                if ($listingId !== null) {
                    $this->openDue->forListing((int) $listingId);
                }

                return $reservation->refresh();
            }

            if ($reason === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Choose a reason.',
                ]);
            }

            if ($reason === PaymentRejectionReason::Other && trim((string) $note) === '') {
                throw ValidationException::withMessages([
                    'note' => 'Add a short note.',
                ]);
            }

            $proof->forceFill([
                'status' => PaymentProofStatus::Rejected,
                'rejection_reason' => $reason,
                'rejection_note' => $note,
                'reviewed_by' => $seller->id,
                'reviewed_at' => now(),
            ])->save();

            $hourFromNow = now()->addHour();
            $openingCap = $reservation->listing?->available_from?->copy()->addHour() ?? $hourFromNow;
            $extension = $openingCap->lessThan($hourFromNow) ? $openingCap : $hourFromNow;
            $currentDue = $reservation->payment_due_at;

            $reservation->payment_status = OrderPaymentStatus::AwaitingPayment;
            $reservation->payment_due_at = $currentDue !== null && $currentDue->greaterThan($extension)
                ? $currentDue
                : $extension;
            $reservation->payment_reminded_at = null;
            $reservation->save();

            $this->events->forReservation($reservation, 'proof_rejected', $seller, $reason->label());

            if ($reservation->buyer !== null) {
                $this->notifier->send(
                    $reservation->buyer,
                    NotificationType::PaymentRejected,
                    NotificationType::PaymentRejected->label(),
                    $reason->label().($note ? ': '.$note : ''),
                    $reservation,
                );
            }

            return $reservation->refresh();
        });
    }
}
