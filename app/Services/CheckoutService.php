<?php

namespace App\Services;

use App\Actions\Reservations\OpenDueReservations;
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
        $listingIds = CartItem::query()
            ->where('buyer_id', $buyer->id)
            ->pluck('listing_id')
            ->unique()
            ->filter();

        $openDue = app(OpenDueReservations::class);

        foreach ($listingIds as $listingId) {
            $openDue->forListing((int) $listingId);
        }

        $removed = CartItem::pruneUnavailable($buyer);

        return DB::transaction(function () use ($buyer, $preference, $fulfillmentNote, $removed): Collection {
            $cartItems = CartItem::query()
                ->where('buyer_id', $buyer->id)
                ->with(['listing.cropType', 'listing.farmerSeller', 'listing.activeTawadRule'])
                ->get()
                ->filter(fn (CartItem $item): bool => $item->listing !== null)
                ->values();

            $upcoming = $cartItems->first(
                fn (CartItem $item): bool => $item->listing->isUpcoming(),
            );

            if ($upcoming !== null) {
                throw ValidationException::withMessages([
                    'cart' => "{$upcoming->listing->title} is not available yet. Remove it from your cart to check out.",
                ]);
            }

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => $removed > 0
                        ? 'The items in your cart are no longer available and were removed.'
                        : 'Your cart is empty.',
                ]);
            }

            $orders = $cartItems
                ->groupBy(fn (CartItem $item): int => (int) $item->listing->farmer_seller_id)
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

        $farmId = $items->first()->listing->farm_id;

        if ($farmId === null) {
            throw ValidationException::withMessages([
                'cart' => 'A listing in your cart is no longer available.',
            ]);
        }

        return $this->placeAppOrder(
            $buyer,
            $seller->id,
            $farmId,
            $lines,
            $preference,
            $fulfillmentNote,
        );
    }

    /**
     * Inserts one Placed app order and holds stock for its lines.
     *
     * Checkout builds fresh prices. A reservation conversion passes the line
     * it stored when the buyer reserved, and the order's created_at is this
     * moment so the 12-hour and 48-hour timers start at conversion.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function placeAppOrder(
        User $buyer,
        int $sellerId,
        int $farmId,
        array $lines,
        FulfillmentPreference $preference,
        ?string $fulfillmentNote,
        ?int $reservationId = null,
    ): Order {
        $subtotal = array_sum(array_column($lines, 'line_subtotal'));
        $tawadTotal = array_sum(array_column($lines, 'tawad_amount'));

        $order = Order::create([
            'order_number' => $this->orderNumbers->generate(),
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $sellerId,
            'farm_id' => $farmId,
            'status' => OrderStatus::Placed,
            'fulfillment_preference' => $preference,
            'fulfillment_note' => $fulfillmentNote,
            'payment_method' => 'cash_on_handover',
            'subtotal' => $subtotal,
            'tawad_total' => $tawadTotal,
            'total' => $subtotal - $tawadTotal,
            'reservation_id' => $reservationId,
        ]);

        $order->items()->createMany($lines);

        $this->holdLines($lines);

        $seller = User::query()->find($sellerId);

        if ($seller !== null) {
            $this->notifier->orderPlaced($seller, $order);
        }

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
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function holdLines(array $lines): void
    {
        foreach ($lines as $line) {
            $listingId = $line['listing_id'] ?? null;

            if ($listingId === null) {
                continue;
            }

            $listing = Listing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->first();

            if ($listing === null) {
                continue;
            }

            $listing->quantity_held = (float) $listing->quantity_held + (float) $line['quantity'];
            $listing->save();
        }
    }
}
