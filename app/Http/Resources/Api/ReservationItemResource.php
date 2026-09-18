<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationItemResource extends JsonResource
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
            'unit' => $this->unit?->value,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_subtotal' => $this->line_subtotal,
        ];
    }
}
