<?php

namespace App\Http\Resources\Api;

use App\Models\FarmAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FarmAnnouncement */
class BuyerFarmAnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
            'image_url' => $this->imageUrl(),
            'published_at' => ($this->starts_at ?? $this->created_at)?->toIso8601String(),
            'farm' => [
                'id' => $this->farm?->id,
                'name' => $this->farm?->name,
                'cover_url' => $this->farm?->coverPhotoUrl(),
            ],
        ];
    }
}
