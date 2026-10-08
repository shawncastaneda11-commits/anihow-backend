<?php

namespace App\Http\Resources\Api;

use App\Enums\Permission;
use App\Models\Farm;
use App\Models\User;
use App\Support\GeoDistance;
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
            'contact_number' => $this->when(
                $this->canSeeContactNumber($request->user()),
                $this->contact_number,
            ),
            'barangay' => $this->barangay,
            'municipality' => $this->municipality,
            'pickup_point' => $this->pickup_point,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'distance_km' => $this->when(
                GeoDistance::requested($request),
                fn (): ?float => GeoDistance::kilometers(
                    $this->latitude !== null ? (float) $this->latitude : null,
                    $this->longitude !== null ? (float) $this->longitude : null,
                    $request,
                ),
            ),
            'is_active' => $this->is_active,
            'features' => [
                'value_added' => $this->allowsValueAdded(),
                'reservations' => $this->allowsReservations(),
                'tawad' => $this->allowsTawad(),
                'walk_in' => $this->allowsWalkIn(),
            ],
            'cover_photo_url' => $this->coverPhotoUrl(),
            'thumbnail_url' => $this->coverThumbnailUrl(),
            'logo_url' => $this->logoUrl(),
            'logo_thumbnail_url' => $this->logoThumbnailUrl(),
            'photos' => FarmPhotoResource::collection($this->whenLoaded('photos')),
            'announcements' => FarmAnnouncementResource::collection($this->whenLoaded('announcements')),
            'farmer_sellers_count' => $this->whenCounted('farmerSellers'),
            'favorites_count' => (int) ($this->favorites_count ?? 0),
            'is_favorited' => $this->when(
                $request->user()?->can(Permission::BrowseMarketplace->value) ?? false,
                fn (): bool => (bool) $this->resource->getAttribute('is_favorited'),
            ),
            'storefronts' => $this->when(
                $this->relationLoaded('farmerSellers'),
                fn (): array => $this->farmerSellers
                    ->map(fn ($seller): array => [
                        'id' => $seller->id,
                        'shop_name' => $seller->shop_name ?: $seller->name,
                        'avatar' => $seller->avatarUrl(),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }

    private function canSeeContactNumber(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can(Permission::ManageFarms->value)) {
            return true;
        }

        return $user->farm_id !== null && (int) $user->farm_id === (int) $this->id;
    }
}
