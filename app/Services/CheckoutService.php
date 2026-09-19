<?php

namespace App\Services;

use App\Enums\FulfillmentPreference;
use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into orders.
 *
 * The cart spans farms and sellers freely. Checkout splits it into one order
 * per farmer-seller, as Shopee does, because an order is an agreement between
 * one buyer and one seller who will meet in person.
 *
 * Tawad rules apply automatically where their conditions are met. Every price
 * is revalidated here against the live crop type, not trusted from the cart:
 * a Super Admin may have raised the floor since the item was added.
 *
 * Stock is held, not deducted. Deduction happens when the seller confirms.
 */
class CheckoutService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @return Collection<int, Order>
     *
     * @throws ValidationException
     */
    public function checkout(
        User $buyer,
        FulfillmentPreference $preference,
        ?string $fulfillmentNote = null,
    ): Collection {
        return DB::transaction(function () use ($buyer, $preference, $fulfillmentNote): Collection {
            $cartItems = CartItem::query()
                ->where('buyer_id', $buyer->id)
                ->with(['listing.cropType', 'listing.farmerSeller', 'listing.activeTawadRule'])
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $orders = $cartItems
                ->groupBy(fn (CartItem $item): int => $item->listing->farmer_seller_id)
                ->map(fn (Collection $items): Order => $this->createOrder(
                    $buyer,
                    $items,
                    $preference,
                    $fulfillmentNote,
                ))
                ->values();

            CartItem::query()->where('buyer_id', $buyer->id)->delete();

            return $orders;
        });
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    private function createOrder(
        User $buyer,
        Collection $items,
        FulfillmentPreference $preference,
        ?string $fulfillmentNote,
    ): Order {
        $seller = $items->first()->listing->farmerSeller;

        if ($seller === null || ! $seller->isActive()) {
            throw ValidationException::withMessages([
                'cart' => 'One of the sellers in your cart is no longer active.',
            ]);
        }

        $lines = $items->map(fn (CartItem $item): array => $this->buildLine($item))->all();

        $subtotal = array_sum(array_column($lines, 'line_subtotal'));
        $tawadTotal = array_sum(array_column($lines, 'tawad_amount'));

        $order = Order::create([
            'order_number' => $this->orderNumbers->generate(),
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $seller->id,
            'farm_id' => $seller->farm_id,
            'status' => OrderStatus::Placed,
            'fulfillment_preference' => $preference,
            'fulfillment_note' => $fulfillmentNote,
            'payment_method' => 'cash_on_handover',
            'subtotal' => $subtotal,
            'tawad_total' => $tawadTotal,
            'total' => $subtotal - $tawadTotal,
        ]);

        $order->items()->createMany($lines);

        $this->holdStock($items);
        $this->notifier->orderPlaced($seller, $order);

        return $order;
    }

    /**
     * One cart line becomes one order item, with every display value
     * snapshotted and every price revalidated against the live crop type.
     *
     * @return array<string, mixed>
     */
    private function buildLine(CartItem $item): array
    {
        $listing = Listing::query()
            ->whereKey($item->listing_id)
            ->with(['cropType', 'activeTawadRule'])
            ->lockForUpdate()
            ->first();

        if ($listing === null) {
            throw ValidationException::withMessages([
                'cart' => 'A listing in your cart is no longer available.',
            ]);
        }

        if (! $listing->status->isVisibleToBuyers() || ! $listing->is_active) {
            throw ValidationException::withMessages([
                'cart' => "{$listing->title} is no longer available.",
            ]);
        }

        $quantity = (float) $item->quantity;

        if (! $listing->hasStockFor($quantity)) {
            throw ValidationException::withMessages([
                'cart' => "{$listing->title} does not have {$quantity} available.",
            ]);
        }

        $cropType = $listing->cropType;
        $unitPrice = (float) $listing->price_per_unit;

        // The Super Admin may have raised the floor since this went in the cart.
        if (! $cropType->allowsPrice($unitPrice)) {
            throw ValidationException::withMessages([
                'cart' => "{$listing->title} is priced below the current floor price and cannot be ordered.",
            ]);
        }

        $lineSubtotal = $unitPrice * $quantity;
        $rule = $listing->activeTawadRule;
        $tawadAmount = 0.0;

        if ($rule !== null && $rule->appliesTo($quantity)) {
            $candidate = $rule->discountFor($quantity);

            // Second floor check, on the discounted unit price. The first ran
            // when the rule was created; the ceiling may have moved since.
            $discountedUnitPrice = ($lineSubtotal - $candidate) / $quantity;

            if ($candidate <= (float) $cropType->max_discount
                && $discountedUnitPrice >= (float) $cropType->floor_price) {
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

    /**
     * Held, not deducted. Cancelling before Confirmed releases this.
     *
     * @param  Collection<int, CartItem>  $items
     */
    private function holdStock(Collection $items): void
    {
        foreach ($items as $item) {
            $listing = Listing::query()
                ->whereKey($item->listing_id)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                continue;
            }

            $listing->quantity_held = (float) $listing->quantity_held + (float) $item->quantity;
            $listing->save();
        }
    }
}
