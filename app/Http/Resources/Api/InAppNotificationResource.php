<?php

namespace App\Http\Resources\Api;

use App\Models\Listing;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InAppNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'title' => $this->title,
            'body' => $this->body,
            'related_id' => $this->related_id,
            'related_type' => $this->relatedKind(),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    private function relatedKind(): ?string
    {
        return match ($this->related_type) {
            Reservation::class => 'reservation',
            Listing::class => 'listing',
            default => $this->related_type,
        };
    }
}
