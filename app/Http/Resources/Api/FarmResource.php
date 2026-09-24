<?php

namespace App\Http\Resources\Api;

use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Farm */
class FarmResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'contact_person' => $this->contact_person,
            'contact_number' => $this->contact_number,
            'barangay' => $this->barangay,
            'municipality' => $this->municipality,
            'pickup_point' => $this->pickup_point,
            'cover_photo_url' => $this->coverPhotoUrl(),
            'photos' => FarmPhotoResource::collection($this->whenLoaded('photos')),
            'farmer_sellers_count' => $this->whenCounted('farmerSellers'),
            'storefronts' => $this->when(
                $this->relationLoaded('farmerSellers'),
                fn (): array => $this->farmerSellers
                    ->map(fn ($seller): array => [
                        'id' => $seller->id,
                        'shop_name' => $seller->shop_name ?: $seller->name,
                        'avatar' => null,
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
