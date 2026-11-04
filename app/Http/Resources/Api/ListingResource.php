<?php

namespace App\Http\Resources\Api;

use App\Models\Listing;
use App\Support\GeoDistance;
use App\Support\Pricing\UnitConverter;
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
            'can_reserve' => $this->canReserve(),
            'crop_type' => new CropTypeResource($this->whenLoaded('cropType')),
            'price_guard' => $this->when(
                $this->isOwnedBy($request),
                fn (): ?array => $this->priceGuardForListingUnit(),
            ),
            'tawad' => $this->visibleTawadRule($request),
            'tawad_paused' => $this->tawadIsPaused($request),
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
            'needs_actual_harvest' => $this->when(
                $this->ownedBy($request),
                (bool) $this->needs_actual_harvest,
            ),
            'expired_with_stock' => $this->when(
                $this->ownedBy($request),
                $this->isExpired() && (float) $this->quantity_available > 0,
            ),
            'stock_baseline' => $this->when(
                $this->ownedBy($request),
                fn (): float => max((float) $this->stock_baseline, (float) $this->quantity_available),
            ),
            'has_harvest_records' => $this->when(
                $this->ownedBy($request),
                fn (): bool => (bool) ($this->harvest_records_exists ?? $this->harvestRecords()->exists()),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function canReserve(): bool
    {
        if (! $this->isUpcoming() || ! ($this->farm?->allowsReservations() ?? true)) {
            return false;
        }

        $seller = $this->farmerSeller;

        if ($seller === null || ! $seller->acceptsOnlinePayment()) {
            return false;
        }

        $hasQr = $seller->relationLoaded('paymentQrs')
            ? $seller->paymentQrs->isNotEmpty()
            : $seller->paymentQrs()->exists();

        if (! $hasQr) {
            return false;
        }

        return $this->available_from !== null && $this->available_from->greaterThan(now()->addHour());
    }

    private function ownedBy(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && (int) $user->id === (int) $this->farmer_seller_id;
    }

    private function isOwnedBy(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && $this->farmer_seller_id === $user->getKey();
    }

    /**
     * The floor and maximum tawad this listing is held to, in the listing's
     * own unit, so the app compares like with like and does no unit maths.
     * max_tawad_per_unit is null when no maximum is set. unit_allowed is
     * false when the listing's unit cannot be checked against the crop.
     *
     * @return array{unit: ?string, floor_per_unit: ?float, max_tawad_per_unit: ?float, unit_allowed: bool}|null
     */
    private function priceGuardForListingUnit(): ?array
    {
        $cropType = $this->cropType;

        if ($cropType === null) {
            return null;
        }

        $guard = $this->resource->priceGuard();
        $cropUnit = $guard->unit ?? $cropType->unit_of_measure;
        $unit = $this->unit ?? $cropUnit;
        $converter = app(UnitConverter::class);

        if (! $cropUnit->convertsTo($unit)) {
            return [
                'unit' => $unit->value,
                'floor_per_unit' => null,
                'max_tawad_per_unit' => null,
                'unit_allowed' => ! $converter->isGuarded($cropType, $this->farm_id),
            ];
        }

        return [
            'unit' => $unit->value,
            'floor_per_unit' => round($converter->priceIn($cropUnit, $unit, $guard->floor), 4),
            'max_tawad_per_unit' => $guard->ceiling === null
                ? null
                : round($converter->priceIn($cropUnit, $unit, $guard->ceiling), 4),
            'unit_allowed' => true,
        ];
    }

    private function tawadIsPaused(Request $request): bool
    {
        if ($this->farm?->allowsTawad() ?? true) {
            return false;
        }

        $user = $request->user();

        if ($user === null || (int) $user->id !== (int) $this->farmer_seller_id) {
            return false;
        }

        return $this->activeTawadRule !== null;
    }

    private function visibleTawadRule(Request $request): mixed
    {
        if (! ($this->farm?->allowsTawad() ?? true) && ! $this->tawadIsPaused($request)) {
            return null;
        }

        return new TawadRuleResource($this->whenLoaded('activeTawadRule'));
    }
}
