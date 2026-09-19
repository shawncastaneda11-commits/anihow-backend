<?php

namespace App\Http\Resources\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A record of a cash handover that already happened. Not a payment document.
 *
 * @mixin Order
 */
class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'fulfillment' => $this->fulfillment_preference->label(),
            'seller' => $this->whenLoaded('farmerSeller', fn (): array => [
                'name' => $this->farmerSeller->name,
                'shop_name' => $this->farmerSeller->shop_name,
                'contact' => $this->farmerSeller->shopContact(),
            ]),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'subtotal' => (float) $this->subtotal,
            'tawad_total' => (float) $this->tawad_total,
            'total' => (float) $this->total,
            'amount_received' => $this->amount_received !== null ? (float) $this->amount_received : null,
        ];
    }
}
