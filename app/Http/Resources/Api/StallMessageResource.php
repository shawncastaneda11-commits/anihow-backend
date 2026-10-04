<?php

namespace App\Http\Resources\Api;

use App\Models\StallMessage;
use App\Support\ImageVariants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StallMessage */
class StallMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $author = $this->author;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'order_id' => $this->order_id,
            'listing_id' => $this->getAttribute('listing_link_id'),
            'listing_title' => $this->listing_title,
            'listing_price_per_unit' => $this->listing_price_per_unit === null
                ? null
                : (string) $this->listing_price_per_unit,
            'listing_unit' => $this->listing_unit,
            'listing_thumbnail_url' => app(ImageVariants::class)->thumbnailUrl($this->listing_thumbnail_path),
            'author' => [
                'id' => $author?->id,
                'name' => $author?->name,
                'role' => $author?->roles->first()?->name,
                'avatar_url' => $author?->avatarUrl(),
            ],
        ];
    }
}
