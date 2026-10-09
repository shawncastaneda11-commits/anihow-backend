<?php

namespace App\Actions\Payments;

use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProofStatus;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\SellerPaymentQr;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\InAppNotifier;
use App\Support\PaymentReference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitPaymentProofAction
{
    public function __construct(
        private ImageVariants $images,
        private RecordPaymentEvent $events,
        private InAppNotifier $notifier,
        private PostPaymentChatNote $chat,
    ) {}

    public function handle(
        User $buyer,
        Order $order,
        string $referenceNumber,
        float|string $amount,
        int $qrId,
        ?UploadedFile $screenshot = null,
    ): PaymentProof {
        return DB::transaction(function () use ($buyer, $order, $referenceNumber, $amount, $qrId, $screenshot): PaymentProof {
            User::query()->whereKey($order->farmer_seller_id)->lockForUpdate()->first();

            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isOwnedByBuyer($buyer)) {
                abort(403);
            }

            if ($order->status === OrderStatus::Cancelled || $order->payment_status !== OrderPaymentStatus::AwaitingPayment) {
                throw ValidationException::withMessages([
                    'order' => 'This order is not waiting for a payment.',
                ]);
            }

            if ($order->payment_due_at === null || now()->greaterThan($order->payment_due_at)) {
                throw ValidationException::withMessages([
                    'order' => 'The payment time has passed.',
                ]);
            }

            $reference = PaymentReference::normalize($referenceNumber);

            if (! preg_match('/^[A-Z0-9]{6,30}$/', $reference)) {
                throw ValidationException::withMessages([
                    'reference_number' => 'Enter 6 to 30 letters or digits.',
                ]);
            }

            $amountValue = round((float) $amount, 2);

            if ($amountValue <= 0 || $amountValue > 9999999.99) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount up to 9999999.99.',
                ]);
            }

            $snapshot = array_map('intval', $order->payment_qr_ids ?? []);

            if (! in_array($qrId, $snapshot, true)) {
                throw ValidationException::withMessages([
                    'qr_id' => 'Choose one of the QR codes for this order.',
                ]);
            }

            $qr = SellerPaymentQr::query()->withTrashed()->find($qrId);

            $duplicate = PaymentProof::query()
                ->where('farmer_seller_id', $order->farmer_seller_id)
                ->where('reference_number', $reference)
                ->whereIn('status', [PaymentProofStatus::Pending, PaymentProofStatus::Accepted])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference_number' => 'This reference number was already used.',
                ]);
            }

            $path = null;

            if ($screenshot !== null) {
                $path = $this->images->store(
                    $screenshot,
                    'payment-proofs/'.$order->id,
                    Storage::disk('local'),
                );
            }

            $proof = PaymentProof::query()->create([
                'order_id' => $order->id,
                'buyer_id' => $buyer->id,
                'farmer_seller_id' => $order->farmer_seller_id,
                'seller_payment_qr_id' => $qr?->id,
                'reference_number' => $reference,
                'amount' => $amountValue,
                'screenshot_path' => $path,
                'status' => PaymentProofStatus::Pending,
            ]);

            $order->payment_status = OrderPaymentStatus::PaymentSent;
            $order->save();

            $this->events->handle($order, 'proof_sent', $buyer, $reference);

            $order->loadMissing('farmerSeller');

            if ($order->farmerSeller !== null) {
                $this->notifier->send(
                    $order->farmerSeller,
                    NotificationType::PaymentProofSubmitted,
                    NotificationType::PaymentProofSubmitted->label(),
                    "{$buyer->name} sent payment proof for order {$order->order_number}.",
                    $order,
                );
            }

            $wallet = $qr?->wallet?->label() ?? 'QR';
            $formatted = number_format($amountValue, 2, '.', '');

            $this->chat->handle(
                $buyer,
                $order,
                "Sent ₱{$formatted} via {$wallet}, ref {$reference}",
            );

            return $proof;
        });
    }
}
