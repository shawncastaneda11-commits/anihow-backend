<?php

namespace App\Http\Resources\Api;

use App\Models\CropCareArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CropCareArticle */
class CropCareArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary(),
            'body' => $this->body,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'image_url' => $this->imageUrl(),
            'published_at' => $this->published_at?->toIso8601String(),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'author_name' => $this->whenLoaded('author', fn (): ?string => $this->author?->name),
            'crop_types' => CropTypeResource::collection($this->whenLoaded('cropTypes')),
        ];
    }
}
