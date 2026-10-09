<?php

namespace App\Http\Resources\Api;

use App\Enums\OrderPaymentStatus;
use App\Models\Reservation;
use App\Models\SellerPaymentQr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'listing_name' => $this->listing_name,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit?->value,
            'unit_price' => (float) $this->unit_price,
            'line_subtotal' => (float) $this->line_subtotal,
            'tawad_amount' => (float) $this->tawad_amount,
            'line_total' => (float) $this->line_total,
            'status' => $this->status->value,
            'cancellation_reason' => $this->cancellation_reason?->value,
            'cancellation_note' => $this->cancellation_note,
            'order_id' => $this->order_id,
            'fulfillment_preference' => $this->fulfillment_preference?->value,
            'fulfillment_note' => $this->fulfillment_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'payment_status' => $this->payment_status?->value,
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refund_reference' => $this->refund_reference,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'latest_proof' => $this->latestProofPayload(),
            'payment_qrs' => $this->when(
                $this->buyerMaySeePaymentQrs($request),
                fn (): array => $this->paymentQrPayload(),
            ),
            'buyer' => $this->whenLoaded('buyer', fn (): array => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'avatar_url' => $this->buyer->avatarUrl(),
            ]),
        ];
    }

    private function buyerMaySeePaymentQrs(Request $request): bool
    {
        $user = $request->user();

        if ($user === null || (int) $user->id !== (int) $this->buyer_id) {
            return false;
        }

        return in_array($this->payment_status, [
            OrderPaymentStatus::AwaitingPayment,
            OrderPaymentStatus::PaymentSent,
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestProofPayload(): ?array
    {
        $proof = $this->latestProof;

        if ($proof === null) {
            return null;
        }

        return [
            'id' => $proof->id,
            'reference_number' => $proof->reference_number,
            'amount' => (float) $proof->amount,
            'wallet' => $proof->paymentQr?->wallet?->value,
            'wallet_label' => $proof->paymentQr?->wallet?->label(),
            'status' => $proof->status->value,
            'rejection_reason' => $proof->rejection_reason?->value,
            'rejection_note' => $proof->rejection_note,
            'account_last4' => $proof->paymentQr?->account_last4,
            'sent_at' => $proof->created_at?->toIso8601String(),
            'reviewed_at' => $proof->reviewed_at?->toIso8601String(),
            'has_screenshot' => $proof->hasScreenshot(),
            'screenshot_url' => $proof->hasScreenshot()
                ? route('payment-proofs.screenshot', $proof)
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentQrPayload(): array
    {
        $ids = array_map('intval', $this->payment_qr_ids ?? []);

        if ($ids === []) {
            return [];
        }

        return SellerPaymentQr::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(fn (SellerPaymentQr $qr): array => [
                'id' => $qr->id,
                'wallet' => $qr->wallet->value,
                'wallet_label' => $qr->wallet->label(),
                'account_name' => $qr->account_name,
                'account_last4' => $qr->account_last4,
                'image_url' => route('payment-qrs.image', $qr),
            ])
            ->all();
    }
}
