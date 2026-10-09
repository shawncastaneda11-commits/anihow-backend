<?php

namespace App\Services;

use App\Actions\Payments\RecordPaymentEvent;
use App\Enums\CancellationReason;
use App\Enums\NotificationType;
use App\Enums\OrderActor;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentRejectionReason;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place an order changes status and the single place listing stock
 * moves. Nothing else may write orders.status or listings.quantity_held.
 *
 * Stock model, which is the part worth reading twice:
 *
 *   Placed      quantity_held increases. The stock is not sellable but is not
 *               yet deducted, so two buyers cannot order the same last kilo.
 *   Confirmed   quantity_available decreases, quantity_held decreases.
 *               Prices and tawad amounts freeze here.
 *   Cancelled   before Confirmed, quantity_held decreases and nothing else.
 *               at or after Confirmed, quantity_available is restored.
 *   Completed   amount_received is required. This is the cash counted at
 *               handover; the system records it and never processes it.
 *
 * A no-show is a cancellation with reason no_show, not a sixth status.
 */
class OrderStateMachine
{
    public function __construct(
        private readonly InAppNotifier $notifier,
        private readonly ?RecordPaymentEvent $paymentEvents = null,
    ) {}

    private function paymentEvents(): RecordPaymentEvent
    {
        return $this->paymentEvents ?? app(RecordPaymentEvent::class);
    }

    /**
     * Advance an order. Returns the refreshed order.
     *
     * @throws ValidationException
     */
    public function transition(
        Order $order,
        OrderStatus $next,
        User|OrderActor $actor,
        ?string $note = null,
        ?CancellationReason $reason = null,
        ?float $amountReceived = null,
    ): Order {
        return DB::transaction(function () use ($order, $next, $actor, $note, $reason, $amountReceived): Order {
            // Re-read under a row lock. Two sellers on two devices hitting
            // Confirm at once would otherwise both pass the check below.
            $order = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $order->status;

            $order->assertCanTransitionTo($next);
            $this->assertActorMayTransition($order, $next, $actor, $reason);

            match ($next) {
                OrderStatus::Confirmed => $this->confirm($order),
                OrderStatus::Ready => $this->assertPaymentConfirmed($order),
                OrderStatus::Completed => $this->complete($order, $amountReceived),
                OrderStatus::Cancelled => $this->cancel($order, $actor, $reason, $note),
                OrderStatus::Placed => throw ValidationException::withMessages([
                    'status' => 'An order cannot be returned to Placed.',
                ]),
            };

            $order->status = $next;
            $order->save();

            $this->recordHistory($order, $from, $next, $actor, $note);
            $this->notify($order, $next);

            return $order->refresh();
        });
    }

    /**
     * Seller confirms. Stock moves from held to deducted, and the prices the
     * buyer saw become the prices of record.
     */
    private function confirm(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->listing_id === null) {
                continue;
            }

            $listing = Listing::query()
                ->whereKey($item->listing_id)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                continue;
            }

            $quantity = (float) $item->quantity;

            if ((float) $listing->quantity_available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "{$listing->title} no longer has enough stock to confirm this order.",
                ]);
            }

            $listing->quantity_available = (float) $listing->quantity_available - $quantity;
            $listing->quantity_held = max(0, (float) $listing->quantity_held - $quantity);
            $listing->save();
        }

        $order->confirmed_at = now();
    }

    /**
     * Tracked orders cannot be packed or handed over until the seller has
     * accepted the buyer's payment. Confirming the order stays allowed.
     */
    private function assertPaymentConfirmed(Order $order): void
    {
        if (! $order->isPaymentTracked()) {
            return;
        }

        if ($order->payment_status !== OrderPaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'status' => "Wait for the buyer's payment to be confirmed.",
            ]);
        }
    }

    /**
     * Handover happened. Cash orders still record what the seller counted.
     * A tracked paid order uses the accepted proof when the seller omits it.
     */
    private function complete(Order $order, ?float $amountReceived): void
    {
        $this->assertPaymentConfirmed($order);

        if ($amountReceived === null && $order->isPaymentTracked() && $order->payment_status === OrderPaymentStatus::Paid) {
            $accepted = $order->proofs()
                ->where('status', PaymentProofStatus::Accepted)
                ->latest('id')
                ->first();
            $amountReceived = $accepted !== null ? (float) $accepted->amount : null;
        }

        if ($amountReceived === null) {
            throw ValidationException::withMessages([
                'amount_received' => 'Record the amount received before completing the order.',
            ]);
        }

        if ($amountReceived < 0) {
            throw ValidationException::withMessages([
                'amount_received' => 'Amount received cannot be negative.',
            ]);
        }

        $order->amount_received = $amountReceived;
        $order->completed_at = now();
    }

    /**
     * Release or restore stock depending on whether the order was confirmed.
     */
    private function cancel(Order $order, User|OrderActor $actor, ?CancellationReason $reason, ?string $note): void
    {
        if ($reason === null) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'A cancellation reason is required.',
            ]);
        }

        $wasDeducted = $order->status->hasDeductedStock();

        foreach ($order->items as $item) {
            if ($item->listing_id === null) {
                continue;
            }

            $listing = Listing::query()
                ->whereKey($item->listing_id)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                continue;
            }

            $quantity = (float) $item->quantity;

            if ($wasDeducted) {
                $listing->quantity_available = (float) $listing->quantity_available + $quantity;
            } else {
                $listing->quantity_held = max(0, (float) $listing->quantity_held - $quantity);
            }

            $listing->save();
        }

        $this->settleTrackedPaymentOnCancel($order, $actor, $reason);

        $order->cancellation_reason = $reason;
        $order->cancellation_note = $note;
        $order->cancelled_by = match (true) {
            $actor === OrderActor::System => OrderActor::System,
            $actor instanceof User && $order->isOwnedByBuyer($actor) => OrderActor::Buyer,
            default => OrderActor::FarmerSeller,
        };
        $order->cancelled_at = now();
    }

    /**
     * A sent or accepted payment becomes a refund. An order still waiting
     * for payment just cancels. PaymentExpired also records its own event.
     */
    private function settleTrackedPaymentOnCancel(Order $order, User|OrderActor $actor, CancellationReason $reason): void
    {
        if ($order->isPaymentTracked() && in_array($order->payment_status, [
            OrderPaymentStatus::PaymentSent,
            OrderPaymentStatus::Paid,
        ], true)) {
            $order->payment_status = OrderPaymentStatus::RefundDue;

            PaymentProof::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentProofStatus::Pending)
                ->update([
                    'status' => PaymentProofStatus::Rejected,
                    'rejection_reason' => PaymentRejectionReason::Other,
                    'rejection_note' => 'Order cancelled',
                    'reviewed_at' => now(),
                    'reviewed_by' => $actor instanceof User ? $actor->id : null,
                ]);

            $actorUser = $actor instanceof User ? $actor : null;
            $this->paymentEvents()->handle($order, 'refund_due', $actorUser);

            $order->loadMissing(['buyer', 'farmerSeller']);

            foreach ([$order->buyer, $order->farmerSeller] as $recipient) {
                if ($recipient === null) {
                    continue;
                }

                $this->notifier->send(
                    $recipient,
                    NotificationType::RefundDue,
                    NotificationType::RefundDue->label(),
                    "Order {$order->order_number} was cancelled and a refund is due.",
                    $order,
                );
            }
        }

        if ($reason === CancellationReason::PaymentExpired) {
            $actorUser = $actor instanceof User ? $actor : null;
            $this->paymentEvents()->handle($order, 'expired', $actorUser);
        }
    }

    /**
     * The farmer-seller advances the status. A buyer may only cancel, and
     * only before the seller confirms. The system may cancel a placed app
     * order left unanswered.
     */
    private function assertActorMayTransition(Order $order, OrderStatus $next, User|OrderActor $actor, ?CancellationReason $reason = null): void
    {
        if ($actor === OrderActor::System) {
            if ($next === OrderStatus::Cancelled && ! $order->isWalkIn() && $order->status === OrderStatus::Placed) {
                return;
            }

            if (
                $next === OrderStatus::Cancelled
                && $reason === CancellationReason::PaymentExpired
                && ! $order->isWalkIn()
                && $order->isPaymentTracked()
                && $order->status === OrderStatus::Confirmed
            ) {
                return;
            }

            throw ValidationException::withMessages([
                'status' => 'The system can only cancel a placed order.',
            ]);
        }

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'status' => 'You are not a party to this order.',
            ]);
        }

        if ($order->isOwnedByFarmer($actor)) {
            return;
        }

        if ($order->isOwnedByBuyer($actor)) {
            if ($next === OrderStatus::Cancelled && $order->canBeCancelledByBuyer()) {
                return;
            }

            throw ValidationException::withMessages([
                'status' => 'A buyer can only cancel an order before it is confirmed.',
            ]);
        }

        throw ValidationException::withMessages([
            'status' => 'You are not a party to this order.',
        ]);
    }

    private function recordHistory(
        Order $order,
        OrderStatus $from,
        OrderStatus $to,
        User|OrderActor $actor,
        ?string $note,
    ): void {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actor instanceof User ? $actor->id : null,
            'note' => $note,
        ]);
    }

    /**
     * Placed notifies the seller; every other state notifies the buyer.
     */
    private function notify(Order $order, OrderStatus $status): void
    {
        if ($status === OrderStatus::Cancelled && $order->cancellation_reason === CancellationReason::PaymentExpired) {
            $order->loadMissing(['buyer', 'farmerSeller']);

            foreach ([$order->buyer, $order->farmerSeller] as $recipient) {
                if ($recipient === null) {
                    continue;
                }

                $this->notifier->send(
                    $recipient,
                    NotificationType::PaymentExpired,
                    NotificationType::PaymentExpired->label(),
                    "Order {$order->order_number} was cancelled because the payment time passed.",
                    $order,
                );
            }

            return;
        }

        $type = NotificationType::forOrderStatus($status);

        if ($type === null) {
            return;
        }

        $recipient = $status === OrderStatus::Placed
            ? $order->farmerSeller
            : $order->buyer;

        if ($recipient === null) {
            return;
        }

        $this->notifier->orderStatusChanged($recipient, $order, $status);
    }
}
