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
        $listing = $this->listing;
        $quantity = (float) $this->quantity;

        if ($listing === null) {
            return [
                'id' => $this->id,
                'quantity' => $quantity,
                'listed_price' => 0.0,
                'line_subtotal' => 0.0,
                'tawad_amount' => 0.0,
                'line_total' => 0.0,
                'listing' => null,
            ];
        }

        $subtotal = $this->lineSubtotal();
        $rule = $listing->effectiveTawadRule();
        $tawad = $rule?->discountFor($quantity) ?? 0.0;

        return [
            'id' => $this->id,
            'quantity' => $quantity,
            'listed_price' => (float) $listing->price_per_unit,
            'line_subtotal' => $subtotal,
            'tawad_amount' => $tawad,
            'line_total' => $subtotal - $tawad,
            'listing' => $this->whenLoaded('listing', fn () => new ListingResource($listing)),
        ];
    }
}
