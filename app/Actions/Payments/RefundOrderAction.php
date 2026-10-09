<?php

namespace App\Actions\Payments;

use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\InAppNotifier;
use App\Support\PaymentReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundOrderAction
{
    public function __construct(
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
    ) {}

    public function handle(User $seller, Order $order, string $reference): Order
    {
        return DB::transaction(function () use ($seller, $order, $reference): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isOwnedByFarmer($seller)) {
                abort(403);
            }

            if ($order->payment_status !== OrderPaymentStatus::RefundDue) {
                throw ValidationException::withMessages([
                    'order' => 'This order does not have a refund due.',
                ]);
            }

            $normalized = PaymentReference::normalize($reference);

            if (! preg_match('/^[A-Z0-9]{6,40}$/', $normalized)) {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Enter 6 to 40 letters or digits.',
                ]);
            }

            $order->forceFill([
                'payment_status' => OrderPaymentStatus::Refunded,
                'refund_reference' => $normalized,
                'refunded_at' => now(),
                'refunded_by' => $seller->id,
            ])->save();

            $this->events->handle($order, 'refunded', $seller, $normalized);

            $order->loadMissing('buyer');

            if ($order->buyer !== null) {
                $this->notifier->send(
                    $order->buyer,
                    NotificationType::RefundCompleted,
                    NotificationType::RefundCompleted->label(),
                    "Your refund for order {$order->order_number} was sent. Reference {$normalized}.",
                    $order,
                );
            }

            return $order->refresh();
        });
    }
}
