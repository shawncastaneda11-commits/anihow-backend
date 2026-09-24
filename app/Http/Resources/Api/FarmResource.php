<?php

namespace App\Http\Resources\Api;

use App\Enums\Permission;
use App\Models\Farm;
use App\Models\User;
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
            'is_active' => $this->is_active,
            'cover_photo_url' => $this->coverPhotoUrl(),
            'photos' => FarmPhotoResource::collection($this->whenLoaded('photos')),
            'announcements' => FarmAnnouncementResource::collection($this->whenLoaded('announcements')),
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
