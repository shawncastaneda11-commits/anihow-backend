<?php

namespace App\Actions\Payments;

use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\PaymentRejectionReason;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewPaymentProofAction
{
    public function __construct(
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
        private PostPaymentChatNote $chat,
    ) {}

    public function handle(
        User $seller,
        Order $order,
        PaymentProof $proof,
        string $decision,
        ?PaymentRejectionReason $reason = null,
        ?string $note = null,
    ): Order {
        return DB::transaction(function () use ($seller, $order, $proof, $decision, $reason, $note): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $proof = PaymentProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();

            if (! $order->isOwnedByFarmer($seller) || $proof->order_id !== $order->id) {
                abort(403);
            }

            if ($proof->status !== PaymentProofStatus::Pending) {
                throw ValidationException::withMessages([
                    'proof' => 'This payment was already reviewed.',
                ]);
            }

            $order->loadMissing('buyer');

            if ($decision === 'accept') {
                $proof->forceFill([
                    'status' => PaymentProofStatus::Accepted,
                    'reviewed_by' => $seller->id,
                    'reviewed_at' => now(),
                ])->save();

                $order->payment_status = OrderPaymentStatus::Paid;
                $order->paid_at = now();
                $order->save();

                $this->events->handle($order, 'proof_accepted', $seller);

                if ($order->buyer !== null) {
                    $this->notifier->send(
                        $order->buyer,
                        NotificationType::PaymentConfirmed,
                        NotificationType::PaymentConfirmed->label(),
                        "Your payment for order {$order->order_number} was confirmed.",
                        $order,
                    );
                }

                $this->chat->handle($seller, $order, 'Payment received. Thank you!');

                return $order->refresh();
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

            $extended = now()->addHour();
            $order->payment_status = OrderPaymentStatus::AwaitingPayment;
            $order->payment_due_at = $order->payment_due_at !== null && $order->payment_due_at->greaterThan($extended)
                ? $order->payment_due_at
                : $extended;
            $order->save();

            $this->events->handle($order, 'proof_rejected', $seller, $reason->label());

            if ($order->buyer !== null) {
                $this->notifier->send(
                    $order->buyer,
                    NotificationType::PaymentRejected,
                    NotificationType::PaymentRejected->label(),
                    $reason->label().($note ? ': '.$note : ''),
                    $order,
                );
            }

            return $order->refresh();
        });
    }
}
