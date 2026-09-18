<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'buyer' => $this->whenLoaded('buyer', fn () => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
            ]),
            'reservation_id' => $this->reservation_id,
            'created_at' => $this->created_at,
        ];
    }
}
