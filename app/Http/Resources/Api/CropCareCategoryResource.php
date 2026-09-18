<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CropCareCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this['id'],
            'name' => $this['name'],
            'slug' => $this['slug'],
            'tips_count' => (int) $this['tips_count'],
        ];
    }
}
