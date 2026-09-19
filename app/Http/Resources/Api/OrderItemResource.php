<?php

namespace App\Http\Resources\Api;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * Three lines, always: the listed price, the tawad, the final total. The
     * listed price is never overwritten.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'listing_name' => $this->listing_name,
            'unit' => $this->unit?->value,
            'quantity' => (float) $this->quantity,
            'listed_price' => (float) $this->unit_price,
            'line_subtotal' => (float) $this->line_subtotal,
            'tawad_type' => $this->tawad_type?->value,
            'tawad_amount' => (float) $this->tawad_amount,
            'line_total' => (float) $this->line_total,
        ];
    }
}
