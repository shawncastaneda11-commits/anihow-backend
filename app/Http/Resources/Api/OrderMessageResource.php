<?php

namespace App\Http\Resources\Api;

use App\Models\OrderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderMessage */
class OrderMessageResource extends JsonResource
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
            'author' => [
                'id' => $author?->id,
                'name' => $author?->name,
                'role' => $author?->roles->first()?->name,
            ],
        ];
    }
}
