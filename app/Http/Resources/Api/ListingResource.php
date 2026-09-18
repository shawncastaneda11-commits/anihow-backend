<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit?->value,
            'unit_label' => $this->unit?->label(),
            'price_per_unit' => $this->price_per_unit,
            'quantity_available' => $this->quantity_available,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'seller' => $this->whenLoaded('farmerSeller', fn () => [
                'id' => $this->farmerSeller->id,
                'shop_name' => $this->farmerSeller->shop_name ?: $this->farmerSeller->name,
                'name' => $this->farmerSeller->name,
                'location' => $this->farmerSeller->location,
                'phone' => $this->farmerSeller->phone,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
