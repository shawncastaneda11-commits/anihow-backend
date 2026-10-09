<?php

namespace App\Http\Resources\Api;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Models\Order;
use App\Models\SellerPaymentQr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('latestProof.paymentQr');

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_next' => array_map(
                fn ($status): string => $status->value,
                $this->status->allowedNext(),
            ),
            'source' => ($this->source ?? OrderSource::App)->value,
            'source_label' => ($this->source ?? OrderSource::App)->label(),
            'is_walk_in' => $this->isWalkIn(),
            // For the seller's own reference. Nobody else is sent it, not even
            // the Super Admin through this API. Decision 20.
            'walk_in_buyer_name' => $this->when(
                (int) $request->user()?->id === (int) $this->farmer_seller_id,
                $this->walk_in_buyer_name,
            ),
            'fulfillment_preference' => $this->fulfillment_preference->value,
            'fulfillment_label' => $this->fulfillment_preference->label(),
            'fulfillment_note' => $this->fulfillment_note,
            'payment_method' => $this->paymentMethodValue(),
            'payment_label' => $this->paymentMethodLabel(),
            'payment_status' => $this->payment_status?->value,
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refund_reference' => $this->when(
                $this->viewerIsParty($request),
                $this->refund_reference,
            ),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'latest_proof' => $this->when(
                $this->viewerIsParty($request),
                fn (): ?array => $this->latestProofPayload(),
            ),
            'payment_qrs' => $this->when(
                $this->buyerMaySeePaymentQrs($request),
                fn (): array => $this->paymentQrPayload(),
            ),
            'subtotal' => (float) $this->subtotal,
            'tawad_total' => (float) $this->tawad_total,
            'total' => (float) $this->total,
            'amount_received' => $this->amount_received !== null ? (float) $this->amount_received : null,
            'cancellation_reason' => $this->cancellation_reason?->value,
            'cancellation_label' => $this->cancellation_reason?->label(),
            'cancellation_note' => $this->cancellation_note,
            'cancelled_by' => $this->cancelled_by?->value,
            'can_be_reviewed' => $this->canBeReviewed(),
            'reservation_id' => $this->reservation_id,
            'from_reservation' => $this->reservation_id !== null,
            'placed_at' => $this->created_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'seller' => $this->whenLoaded('farmerSeller', fn (): array => [
                'id' => $this->farmerSeller->id,
                'name' => $this->farmerSeller->name,
                'shop_name' => $this->farmerSeller->shop_name,
                'contact' => $this->farmerSeller->shopContact(),
                'avatar_url' => $this->farmerSeller->avatarUrl(),
            ]),
            // Null on a walk-in. whenLoaded() returns null for a loaded but
            // empty relation without calling the closure, so no buyer is fine.
            'buyer' => $this->whenLoaded('buyer', fn (): array => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'contact' => $this->buyer->phone,
                'avatar_url' => $this->buyer->avatarUrl(),
            ]),
            'history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(
                fn ($entry): array => [
                    'from' => $entry->from_status?->value,
                    'to' => $entry->to_status->value,
                    'note' => $entry->note,
                    'at' => $entry->created_at?->toIso8601String(),
                ],
            )),
            'review' => new ReviewResource($this->whenLoaded('review')),
        ];
    }

    private function viewerIsParty(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->isOwnedByBuyer($user) || $this->isOwnedByFarmer($user);
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
