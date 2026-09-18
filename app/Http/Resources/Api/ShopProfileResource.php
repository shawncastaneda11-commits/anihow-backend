<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_name' => $this->shop_name ?: $this->name,
            'name' => $this->name,
            'bio' => $this->bio,
            'location' => $this->location,
            'contact' => $this->shopContact(),
            'average_rating' => $this->formatAverageRating(),
            'reviews_count' => (int) ($this->reviews_received_count ?? 0),
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
        ];
    }

    private function formatAverageRating(): ?string
    {
        $average = $this->reviews_received_avg_rating ?? $this->averageRating();

        if ($average === null) {
            return null;
        }

        return number_format((float) $average, 2, '.', '');
    }
}
