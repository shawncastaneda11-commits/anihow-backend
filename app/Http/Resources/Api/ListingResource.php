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
        $sellerLoaded = $this->relationLoaded('farmerSeller');
        $averageRating = $sellerLoaded ? $this->sellerAverageRating() : null;
        $reviewsCount = $sellerLoaded ? $this->sellerReviewsCount() : 0;

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
            'seller' => $this->when($sellerLoaded, fn () => [
                'id' => $this->farmerSeller->id,
                'shop_name' => $this->farmerSeller->shop_name ?: $this->farmerSeller->name,
                'name' => $this->farmerSeller->name,
                'location' => $this->farmerSeller->location,
                'phone' => $this->farmerSeller->phone,
                'average_rating' => $averageRating,
                'reviews_count' => $reviewsCount,
            ]),
            'average_rating' => $this->when($sellerLoaded, fn () => $averageRating),
            'reviews_count' => $this->when($sellerLoaded, fn () => $reviewsCount),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function sellerAverageRating(): ?string
    {
        return $this->farmerSeller->averageRating();
    }

    private function sellerReviewsCount(): int
    {
        return (int) ($this->farmerSeller->reviews_received_count ?? 0);
    }
}
