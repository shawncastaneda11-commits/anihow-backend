<?php

namespace App\Http\Resources\Api;

use App\Enums\Permission;
use App\Models\ShopFavorite;
use App\Support\GeoDistance;
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
            'avatar_url' => $this->avatarUrl(),
            'cover_url' => $this->coverUrl(),
            'distance_km' => $this->when(
                $this->relationLoaded('farm') && GeoDistance::requested($request),
                fn (): ?float => GeoDistance::kilometers(
                    $this->farm?->latitude !== null ? (float) $this->farm->latitude : null,
                    $this->farm?->longitude !== null ? (float) $this->farm->longitude : null,
                    $request,
                ),
            ),
            'contact' => $this->when(
                $request->user() !== null && (int) $request->user()->id === (int) $this->id,
                fn (): ?string => $this->shopContact(),
            ),
            'average_rating' => $this->averageRating(),
            'reviews_count' => (int) ($this->reviews_received_count ?? 0),
            'listings' => ListingResource::collection($this->whenLoaded('listings')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'is_favorited' => $this->when(
                $request->user()?->can(Permission::BrowseMarketplace->value) ?? false,
                fn (): bool => array_key_exists('is_favorited', $this->getAttributes())
                    ? (bool) $this->getAttribute('is_favorited')
                    : ShopFavorite::query()
                        ->where('buyer_id', $request->user()?->id)
                        ->where('farmer_seller_id', $this->id)
                        ->exists(),
            ),
        ];
    }
}
