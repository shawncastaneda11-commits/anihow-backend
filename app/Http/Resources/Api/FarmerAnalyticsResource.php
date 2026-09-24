<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerAnalyticsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->resource;

        return [
            'period' => $payload['period'],
            'window_start' => $payload['window_start'],
            'window_end' => $payload['window_end'],
            'summary' => $payload['summary'],
            'sales_per_period' => $payload['sales_per_period'],
            'units_per_crop_type' => $payload['units_per_crop_type'],
            'best_selling' => $payload['best_selling'],
            'walk_in_share' => $payload['walk_in_share'],
        ];
    }
}
