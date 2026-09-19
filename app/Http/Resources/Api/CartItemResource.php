<?php

namespace App\Http\Resources\Api;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The cart preview shows what the tawad would be if the buyer checked out now.
 * It is indicative: the binding calculation happens at checkout against the
 * live crop type.
 *
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (float) $this->quantity;
        $subtotal = $this->lineSubtotal();
        $rule = $this->listing->activeTawadRule;
        $tawad = $rule?->discountFor($quantity) ?? 0.0;

        return [
            'id' => $this->id,
            'quantity' => $quantity,
            'listed_price' => (float) $this->listing->price_per_unit,
            'line_subtotal' => $subtotal,
            'tawad_amount' => $tawad,
            'line_total' => $subtotal - $tawad,
            'listing' => new ListingResource($this->whenLoaded('listing')),
        ];
    }
}
