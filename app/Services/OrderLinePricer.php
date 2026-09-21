<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Validation\ValidationException;

/**
 * Prices one order line. The only place a listing's price, floor, and tawad
 * become the numbers an order records.
 *
 * Two callers: CheckoutService for app orders, RecordWalkInSaleAction for
 * walk-in sales. The spec says a walk-in is priced on the same rules as an app
 * order, so both go through here and cannot drift apart. Decision 29.
 *
 * The listing must be loaded with cropType, activeTawadRule, and
 * farm.cropTypeOverrides, and locked by the caller. This class checks prices;
 * the caller checks availability and stock, because those messages differ
 * between a cart and a stall.
 */
class OrderLinePricer
{
    /**
     * @return array<string, mixed> the order_items row
     *
     * @throws ValidationException when the listing is priced below its farm's
     *                             effective floor
     */
    public function price(
        Listing $listing,
        float $quantity,
        string $errorKey,
        string $belowFloorMessage,
    ): array {
        $cropType = $listing->cropType;
        $guard = $listing->priceGuard();
        $unitPrice = (float) $listing->price_per_unit;

        // A floor may have risen since the listing was priced, the system one
        // or the farm's.
        if (! $guard->allowsPrice($unitPrice)) {
            throw ValidationException::withMessages([
                $errorKey => $belowFloorMessage,
            ]);
        }

        $lineSubtotal = $unitPrice * $quantity;
        $rule = $listing->activeTawadRule;
        $tawadAmount = 0.0;

        if ($rule !== null && $rule->appliesTo($quantity)) {
            $candidate = $rule->discountFor($quantity);

            // Second floor check, on the discounted unit price. The first ran
            // when the rule was created; the floor or the ceiling may have
            // moved since. A rule that fails is skipped, not rejected: the
            // line goes through at the listed price. Decision 15.
            $discountedUnitPrice = ($lineSubtotal - $candidate) / $quantity;

            if ($candidate <= $guard->ceiling
                && $discountedUnitPrice >= $guard->floor) {
                $tawadAmount = $candidate;
            }
        }

        return [
            'listing_id' => $listing->id,
            'crop_type_id' => $cropType->id,
            'listing_name' => $listing->title,
            'unit' => $cropType->unit_of_measure,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_subtotal' => $lineSubtotal,
            'tawad_rule_id' => $tawadAmount > 0 ? $rule->id : null,
            'tawad_type' => $tawadAmount > 0 ? $rule->type : null,
            'tawad_amount' => $tawadAmount,
            'line_total' => $lineSubtotal - $tawadAmount,
        ];
    }
}
