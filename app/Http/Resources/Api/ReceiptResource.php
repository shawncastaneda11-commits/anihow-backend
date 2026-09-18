<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items, collect());

        return [
            'reservation_id' => $this->id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'seller' => $this->whenLoaded('farmerSeller', fn () => [
                'id' => $this->farmerSeller->id,
                'shop_name' => $this->farmerSeller->shop_name ?: $this->farmerSeller->name,
                'name' => $this->farmerSeller->name,
                'location' => $this->farmerSeller->location,
                'contact' => $this->farmerSeller->shopContact(),
            ]),
            'items' => ReservationItemResource::collection($this->whenLoaded('items')),
            'item_count' => $items->count(),
            'total' => $this->total,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'ready_at' => $this->ready_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
        ];
    }
}
