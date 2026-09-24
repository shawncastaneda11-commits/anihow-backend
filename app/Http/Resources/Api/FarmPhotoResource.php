<?php

namespace App\Http\Resources\Api;

use App\Models\FarmPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FarmPhoto */
class FarmPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'caption' => $this->caption,
        ];
    }
}
