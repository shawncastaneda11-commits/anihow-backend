<?php

namespace App\Http\Resources\Api;

use App\Models\TawadRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TawadRule */
class TawadRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'discount_amount' => (float) $this->discount_amount,
            'min_quantity' => $this->min_quantity !== null ? (float) $this->min_quantity : null,
            'is_active' => $this->is_active,
        ];
    }
}
