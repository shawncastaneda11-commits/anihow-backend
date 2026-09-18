<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'listing' => new ListingResource($this->whenLoaded('listing')),
            'created_at' => $this->created_at,
        ];
    }
}
