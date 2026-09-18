<?php

namespace App\Http\Resources\Api;

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
            'related_type' => $this->related_type,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
