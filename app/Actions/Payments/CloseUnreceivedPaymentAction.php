<?php

namespace App\Actions\Payments;

use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A cancellation turns a sent payment into a refund due, even when the seller
 * never confirmed receiving it. If the money never reached the seller there is
 * nothing to return, so the seller closes the refund instead of recording one.
 */
class CloseUnreceivedPaymentAction
{
    public function __construct(
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
    ) {}

    public function forOrder(User $seller, Order $order): Order
    {
        return DB::transaction(function () use ($seller, $order): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->ensureClosable($seller, (int) $order->farmer_seller_id, $order->payment_status, $order->paid_at !== null);

            $order->forceFill(['payment_status' => OrderPaymentStatus::NotReceived])->save();

            $this->events->handle($order, 'not_received', $seller);

            $order->loadMissing('buyer');
            $this->notifyBuyer($order->buyer, "order {$order->order_number}", $order, 'message the seller or report the payment');

            return $order->refresh();
        });
    }

    public function forReservation(User $seller, Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($seller, $reservation): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            $this->ensureClosable($seller, (int) $reservation->farmer_seller_id, $reservation->payment_status, $reservation->paid_at !== null);

            $reservation->forceFill(['payment_status' => OrderPaymentStatus::NotReceived])->save();

            $this->events->forReservation($reservation, 'not_received', $seller);

            $reservation->loadMissing('buyer');
            $this->notifyBuyer($reservation->buyer, "reservation for {$reservation->listing_name}", $reservation, 'message the seller');

            return $reservation->refresh();
        });
    }

    private function ensureClosable(User $seller, int $sellerId, ?OrderPaymentStatus $status, bool $confirmedReceived): void
    {
        if ($sellerId !== (int) $seller->id) {
            abort(403);
        }

        if ($status !== OrderPaymentStatus::RefundDue) {
            throw ValidationException::withMessages([
                'payment' => 'This payment does not have a refund due.',
            ]);
        }

        if ($confirmedReceived) {
            throw ValidationException::withMessages([
                'payment' => 'You confirmed receiving this payment, so it must be refunded.',
            ]);
        }
    }

    private function notifyBuyer(?User $buyer, string $subject, Order|Reservation $related, string $nextStep): void
    {
        if ($buyer === null) {
            return;
        }

        $this->notifier->send(
            $buyer,
            NotificationType::PaymentRejected,
            'Payment not received',
            "The seller says your payment for the {$subject} never arrived, so no refund will be sent. If you did pay, {$nextStep}.",
            $related,
        );
    }
}
