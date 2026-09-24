<?php

namespace App\Http\Resources\Api;

use App\Enums\Permission;
use App\Models\ShopFavorite;
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
            'average_rating' => $this->averageRating(),
            'reviews_count' => (int) ($this->reviews_received_count ?? 0),
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'is_favorited' => $this->when(
                $request->user()?->can(Permission::BrowseMarketplace->value) ?? false,
                fn (): bool => ShopFavorite::query()
                    ->where('buyer_id', $request->user()?->id)
                    ->where('farmer_seller_id', $this->id)
                    ->exists(),
            ),
        ];
    }
}
