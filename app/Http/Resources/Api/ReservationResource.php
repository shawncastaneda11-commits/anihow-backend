<?php

namespace App\Http\Resources\Api;

use App\Models\Reservation;
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
            'buyer' => $this->whenLoaded('buyer', fn (): array => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'avatar_url' => $this->buyer->avatarUrl(),
            ]),
        ];
    }
}
