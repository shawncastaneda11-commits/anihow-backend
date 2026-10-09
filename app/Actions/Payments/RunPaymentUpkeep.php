<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\CancelReservation;
use App\Enums\CancellationReason;
use App\Enums\NotificationType;
use App\Enums\OrderActor;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Services\OrderStateMachine;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RunPaymentUpkeep
{
    public function __construct(
        private OrderStateMachine $orders,
        private InAppNotifier $notifier,
        private CancelReservation $cancelReservation,
    ) {}

    public function handle(): void
    {
        $this->expireUnpaid();
        $this->expireUnpaidReservations();
        $this->remindBuyers();
        $this->remindReservationBuyers();
        $this->remindSellers();
    }

    private function expireUnpaid(): void
    {
        Order::query()
            ->where('payment_status', OrderPaymentStatus::AwaitingPayment)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->orderBy('id')
            ->each(function (Order $order): void {
                try {
                    $this->orders->transition(
                        $order,
                        OrderStatus::Cancelled,
                        OrderActor::System,
                        note: 'Payment time expired.',
                        reason: CancellationReason::PaymentExpired,
                    );
                } catch (ValidationException) {
                    Log::info('Skipping an unpaid order that changed during payment upkeep.', [
                        'order_id' => $order->id,
                    ]);
                }
            });
    }

    private function expireUnpaidReservations(): void
    {
        Reservation::query()
            ->where('status', ReservationStatus::Active)
            ->where('payment_status', OrderPaymentStatus::AwaitingPayment)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->orderBy('id')
            ->each(function (Reservation $reservation): void {
                $this->cancelReservation->handle(
                    $reservation,
                    ReservationCancellationReason::PaymentExpired,
                );
            });
    }

    private function remindBuyers(): void
    {
        Order::query()
            ->with('buyer')
            ->where('payment_status', OrderPaymentStatus::AwaitingPayment)
            ->whereNull('payment_reminded_at')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '>', now())
            ->where('payment_due_at', '<=', now()->addHour())
            ->orderBy('id')
            ->each(function (Order $order): void {
                if ($order->created_at === null || $order->payment_due_at === null) {
                    return;
                }

                if (! $order->created_at->lt($order->payment_due_at->copy()->subHour())) {
                    return;
                }

                $claimed = Order::query()
                    ->whereKey($order->id)
                    ->where('payment_status', OrderPaymentStatus::AwaitingPayment)
                    ->whereNull('payment_reminded_at')
                    ->update(['payment_reminded_at' => now()]);

                if ($claimed !== 1 || $order->buyer === null) {
                    return;
                }

                $this->notifier->send(
                    $order->buyer,
                    NotificationType::PaymentDueSoon,
                    NotificationType::PaymentDueSoon->label(),
                    "Pay for order {$order->order_number} within the next hour.",
                    $order,
                );
            });
    }

    private function remindReservationBuyers(): void
    {
        Reservation::query()
            ->with('buyer')
            ->where('status', ReservationStatus::Active)
            ->where('payment_status', OrderPaymentStatus::AwaitingPayment)
            ->whereNull('payment_reminded_at')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '>', now())
            ->where('payment_due_at', '<=', now()->addHour())
            ->orderBy('id')
            ->each(function (Reservation $reservation): void {
                if ($reservation->created_at === null || $reservation->payment_due_at === null) {
                    return;
                }

                if (! $reservation->created_at->lt($reservation->payment_due_at->copy()->subHour())) {
                    return;
                }

                $claimed = Reservation::query()
                    ->whereKey($reservation->id)
                    ->where('payment_status', OrderPaymentStatus::AwaitingPayment)
                    ->whereNull('payment_reminded_at')
                    ->update(['payment_reminded_at' => now()]);

                if ($claimed !== 1 || $reservation->buyer === null) {
                    return;
                }

                $this->notifier->send(
                    $reservation->buyer,
                    NotificationType::PaymentDueSoon,
                    NotificationType::PaymentDueSoon->label(),
                    "Pay for your reservation of {$reservation->listing_name} within the next hour.",
                    $reservation,
                );
            });
    }

    private function remindSellers(): void
    {
        $this->remindSellerAfter(12, 'reminder_12h_at');
        $this->remindSellerAfter(24, 'reminder_24h_at');
    }

    private function remindSellerAfter(int $hours, string $column): void
    {
        PaymentProof::query()
            ->with(['order', 'reservation', 'farmerSeller'])
            ->where('status', PaymentProofStatus::Pending)
            ->whereNull($column)
            ->where('created_at', '<=', now()->subHours($hours))
            ->orderBy('id')
            ->each(function (PaymentProof $proof) use ($column): void {
                $claimed = PaymentProof::query()
                    ->whereKey($proof->id)
                    ->where('status', PaymentProofStatus::Pending)
                    ->whereNull($column)
                    ->update([$column => now()]);

                if ($claimed !== 1 || $proof->farmerSeller === null) {
                    return;
                }

                if ($proof->order !== null) {
                    $this->notifier->send(
                        $proof->farmerSeller,
                        NotificationType::PaymentCheckReminder,
                        NotificationType::PaymentCheckReminder->label(),
                        "Payment proof for order {$proof->order->order_number} is still waiting for you.",
                        $proof->order,
                    );

                    return;
                }

                if ($proof->reservation === null) {
                    return;
                }

                $this->notifier->send(
                    $proof->farmerSeller,
                    NotificationType::PaymentCheckReminder,
                    NotificationType::PaymentCheckReminder->label(),
                    "Payment proof for {$proof->reservation->listing_name} is still waiting for you.",
                    $proof->reservation,
                );
            });
    }
}
