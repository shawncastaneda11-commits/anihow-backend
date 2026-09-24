<?php

namespace App\Http\Resources\Api;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price_per_unit' => (float) $this->price_per_unit,
            'quantity_available' => (float) $this->quantity_available,
            'sellable_quantity' => $this->sellableQuantity(),
            'is_active' => $this->is_active,
            'status' => $this->status->value,
            'image_url' => $this->imageUrl(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'crop_type' => new CropTypeResource($this->whenLoaded('cropType')),
            'tawad' => new TawadRuleResource($this->whenLoaded('activeTawadRule')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'seller' => $this->whenLoaded('farmerSeller', fn (): array => [
                'id' => $this->farmerSeller->id,
                'name' => $this->farmerSeller->name,
                'shop_name' => $this->farmerSeller->shop_name,
                'average_rating' => $this->farmerSeller->averageRating(),
                'reviews_count' => $this->farmerSeller->reviews_received_count ?? null,
            ]),
            'takedown_reason' => $this->when(
                $this->status->value === 'taken_down',
                $this->takedown_reason,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
