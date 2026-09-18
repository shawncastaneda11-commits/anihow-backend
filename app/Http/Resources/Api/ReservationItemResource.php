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
            'quantity' => number_format((float) $this->quantity, 2, '.', ''),
            'unit_price' => number_format((float) $this->unit_price, 2, '.', ''),
            'line_subtotal' => number_format((float) $this->line_subtotal, 2, '.', ''),
        ];
    }
}
