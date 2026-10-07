<?php

namespace App\Http\Resources\Api;

use App\Models\Listing;
use App\Support\GeoDistance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'unit' => $this->unit?->value,
            'unit_label' => $this->unit?->label(),
            'price_per_unit' => (float) $this->price_per_unit,
            'quantity_available' => (float) $this->quantity_available,
            'sellable_quantity' => $this->sellableQuantity(),
            'min_order_quantity' => (float) $this->min_order_quantity,
            'order_step' => (float) $this->order_step,
            'reserved_quantity' => $this->when(
                $this->resource instanceof Listing && array_key_exists('reserved_quantity', $this->resource->getAttributes()),
                fn (): float => (float) ($this->reserved_quantity ?? 0),
            ),
            'active_reservations_count' => $this->when(
                $this->resource instanceof Listing && array_key_exists('active_reservations_count', $this->resource->getAttributes()),
                fn (): int => (int) ($this->active_reservations_count ?? 0),
            ),
            'is_active' => $this->is_active,
            'status' => $this->status->value,
            'available_from' => $this->available_from?->toIso8601String(),
            'available_until' => $this->available_until?->toIso8601String(),
            'harvested_on' => $this->harvested_on?->toDateString(),
            'is_upcoming' => $this->isUpcoming(),
            'availability_state' => $this->availabilityState(),
            'category' => $this->cropType === null ? null : [
                'value' => $this->cropType->category->value,
                'label' => $this->cropType->category->label(),
                'label_fil' => $this->cropType->category->labelFil(),
            ],
            'growing_method' => $this->growing_method?->value,
            'organic_badge' => $this->organicBadge(),
            'organic_certifier' => $this->organicBadge() === 'certified'
                ? $this->farm?->organic_certifier
                : null,
            'distance_km' => $this->when(
                $this->relationLoaded('farm') && GeoDistance::requested($request),
                fn (): ?float => GeoDistance::kilometers(
                    $this->farm?->latitude !== null ? (float) $this->farm->latitude : null,
                    $this->farm?->longitude !== null ? (float) $this->farm->longitude : null,
                    $request,
                ),
            ),
            'image_url' => $this->imageUrl(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'crop_type' => new CropTypeResource($this->whenLoaded('cropType')),
            'tawad' => new TawadRuleResource($this->whenLoaded('activeTawadRule')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'seller' => $this->whenLoaded('farmerSeller', fn (): array => [
                'id' => $this->farmerSeller->id,
                'name' => $this->farmerSeller->name,
                'shop_name' => $this->farmerSeller->shop_name,
                'average_rating' => $this->farmerSeller->averageRating(),
                'reviews_count' => $this->farmerSeller->reviews_received_count ?? null,
                'accepts_online_payment' => $this->farmerSeller->acceptsOnlinePayment(),
            ]),
            'takedown_reason' => $this->when(
                $this->status->value === 'taken_down',
                $this->takedown_reason,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
