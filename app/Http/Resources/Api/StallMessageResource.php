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
            'attachment' => $this->attachmentPayload(),
            'author' => [
                'id' => $author?->id,
                'name' => $author?->name,
                'role' => $author?->roles->first()?->name,
                'avatar_url' => $author?->avatarUrl(),
            ],
        ];
    }

    /**
     * @return array{name: string|null, mime: string|null, size: int|null, url: string, thumbnail_url: string|null}|null
     */
    private function attachmentPayload(): ?array
    {
        if (! filled($this->attachment_path)) {
            return null;
        }

        $photo = str_starts_with((string) $this->attachment_mime, 'image/');

        return [
            'name' => $this->attachment_name,
            'mime' => $this->attachment_mime,
            'size' => $this->attachment_size === null ? null : (int) $this->attachment_size,
            'url' => route('chat.attachments.show', $this->resource),
            'thumbnail_url' => $photo
                ? route('chat.attachments.show', ['stallMessage' => $this->resource, 'variant' => 'thumb'])
                : null,
        ];
    }
}
