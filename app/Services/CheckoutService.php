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
 * is revalidated against the listing's farm's effective floor and ceiling, not
 * trusted from the cart: the Super Admin may have raised the system floor, or
 * the farm its own, since the item was added. The pricing itself lives in
 * OrderLinePricer, shared with walk-in sales.
 *
 * Stock is held, not deducted. Deduction happens when the seller confirms.
 */
class CheckoutService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly InAppNotifier $notifier,
        private readonly OrderLinePricer $pricer,
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
     * One cart line becomes one order item. Availability and stock are checked
     * here, in the buyer's terms; the price, floor, and tawad are the pricer's.
     *
     * @return array<string, mixed>
     */
    private function buildLine(CartItem $item): array
    {
        // farm.cropTypeOverrides is loaded with the locked row so the guard
        // resolves from memory. This runs once per cart line.
        $listing = Listing::query()
            ->whereKey($item->listing_id)
            ->with(['cropType', 'activeTawadRule', 'farm.cropTypeOverrides'])
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

        return $this->pricer->price(
            $listing,
            $quantity,
            'cart',
            "{$listing->title} is priced below the current floor price and cannot be ordered.",
        );
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
