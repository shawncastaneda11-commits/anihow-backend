<?php

namespace App\Http\Resources\Api;

use App\Models\CropType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CropType */
class CropTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'label_en' => $this->label_en,
            'label_fil' => $this->label_fil,
            'unit_of_measure' => $this->unit_of_measure->value,
            'unit_label' => $this->unit_of_measure->label(),
            'floor_price' => (float) $this->floor_price,
            'max_discount' => (float) $this->max_discount,
            'listings_count' => $this->whenCounted('listings'),
        ];
    }
}
