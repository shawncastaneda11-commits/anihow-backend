<?php

namespace App\Http\Resources\Api;

use App\Models\StallConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StallConversation */
class StallConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seller = $this->farmerSeller;
        $latest = $this->latestMessage;

        return [
            'id' => $this->id,
            'buyer_id' => $this->buyer_id,
            'farmer_seller_id' => $this->farmer_seller_id,
            'shop_name' => $seller?->shop_name ?: $seller?->name,
            'buyer_name' => $this->buyer?->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'latest_message' => $latest === null ? null : [
                'body' => $latest->body,
                'created_at' => $latest->created_at?->toIso8601String(),
            ],
        ];
    }
}
