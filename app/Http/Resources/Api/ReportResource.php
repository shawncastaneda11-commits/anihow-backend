<?php

namespace App\Http\Resources\Api;

use App\Models\Listing;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Report */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetType(),
            'reason' => $this->reason instanceof \BackedEnum ? $this->reason->value : $this->reason,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function targetType(): string
    {
        return match ($this->reportable_type) {
            Listing::class => 'listing',
            Review::class => 'review',
            default => class_basename((string) $this->reportable_type),
        };
    }
}
