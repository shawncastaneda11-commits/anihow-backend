<?php

namespace App\Http\Resources\Api;

use App\Enums\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'total' => $this->money($this->total),
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_by' => $this->cancelled_by?->value,
            'can_review' => $this->canReview($request),
            'review' => $this->when(
                $this->relationLoaded('review'),
                fn () => $this->review === null ? null : new ReviewResource($this->review),
            ),
            'buyer' => $this->whenLoaded('buyer', fn () => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'phone' => $this->buyer->phone,
            ]),
            'seller' => $this->whenLoaded('farmerSeller', fn () => [
                'id' => $this->farmerSeller->id,
                'name' => $this->farmerSeller->name,
                'shop_name' => $this->farmerSeller->shop_name ?: $this->farmerSeller->name,
                'location' => $this->farmerSeller->location,
                'phone' => $this->farmerSeller->phone,
                'contact' => $this->farmerSeller->shopContact(),
            ]),
            'items' => ReservationItemResource::collection($this->whenLoaded('items')),
            'ready_at' => $this->ready_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function canReview(Request $request): bool
    {
        $user = $request->user();

        if ($user === null || ! $user->isBuyer() || ! $this->isOwnedByBuyer($user)) {
            return false;
        }

        if ($this->status !== ReservationStatus::Completed) {
            return false;
        }

        if (! $this->relationLoaded('review')) {
            return false;
        }

        return $this->review === null;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
