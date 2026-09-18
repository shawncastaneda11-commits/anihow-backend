<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CropCareArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt(),
            'body' => $this->when(
                $request->routeIs(
                    'farmer.crop-care.show',
                    'farmer.crop-care.store',
                    'farmer.crop-care.update',
                    'farmer.crop-care.mine',
                ),
                $this->body,
            ),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'is_official' => $this->isOfficial(),
            'can_edit' => $user !== null && $user->can('update', $this->resource),
            'author' => $this->when(
                $this->relationLoaded('author') && $this->author !== null,
                fn (): array => [
                    'id' => $this->author->id,
                    'name' => $this->author->name,
                    'shop_name' => $this->author->shop_name,
                ],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
