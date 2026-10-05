<?php

namespace App\Http\Resources\Api;

use App\Models\FarmFavorite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FarmFavorite */
class FarmFavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $farm = $this->farm;
        $place = collect([$farm?->barangay, $farm?->municipality])
            ->filter(fn ($part) => is_string($part) && $part !== '')
            ->implode(', ');

        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'name' => $farm?->name,
            'place' => $place === '' ? null : $place,
            'cover_photo_url' => $farm?->coverPhotoUrl(),
            'thumbnail_url' => $farm?->coverThumbnailUrl(),
            'sellers_count' => (int) ($farm?->farmer_sellers_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
