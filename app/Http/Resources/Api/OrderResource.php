<?php

namespace App\Http\Resources\Api;

use App\Enums\OrderSource;
use App\Models\Order;
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
            'payment_method' => $this->payment_method,
            'subtotal' => (float) $this->subtotal,
            'tawad_total' => (float) $this->tawad_total,
            'total' => (float) $this->total,
            'amount_received' => $this->amount_received !== null ? (float) $this->amount_received : null,
            'cancellation_reason' => $this->cancellation_reason?->value,
            'cancellation_label' => $this->cancellation_reason?->label(),
            'cancellation_note' => $this->cancellation_note,
            'cancelled_by' => $this->cancelled_by?->value,
            'can_be_reviewed' => $this->canBeReviewed(),
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
            ]),
            // Null on a walk-in. whenLoaded() returns null for a loaded but
            // empty relation without calling the closure, so no buyer is fine.
            'buyer' => $this->whenLoaded('buyer', fn (): array => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'contact' => $this->buyer->phone,
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
}
