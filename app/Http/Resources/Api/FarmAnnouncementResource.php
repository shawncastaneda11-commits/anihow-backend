<?php

namespace App\Http\Resources\Api;

use App\Models\FarmAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FarmAnnouncement */
class FarmAnnouncementResource extends JsonResource
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
            'audience' => $this->audience->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
