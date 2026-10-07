<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopFavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farmer_seller_id' => $this->farmer_seller_id,
            'shop' => new ShopProfileResource($this->whenLoaded('farmerSeller')),
            'created_at' => $this->created_at,
        ];
    }
}
