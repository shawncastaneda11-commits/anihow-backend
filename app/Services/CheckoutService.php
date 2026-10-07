<?php

namespace App\Services;

use App\Actions\Chat\SendStallMessage;
use App\Actions\Reservations\OpenDueReservations;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\Order;
use App\Models\StallConversation;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

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
     * @param  array<int, array{seller_id?: int, method?: string}>  $payments
     * @return Collection<int, Order>
     *
     * @throws ValidationException
     */
    public function checkout(
        User $buyer,
        FulfillmentPreference $preference,
        ?string $fulfillmentNote = null,
        array $payments = [],
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

        $orders = DB::transaction(function () use ($buyer, $preference, $fulfillmentNote, $removed, $payments): Collection {
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

            $choices = $this->paymentChoices($cartItems, $payments);

            $orders = $cartItems
                ->groupBy(fn (CartItem $item): int => (int) $item->listing->farmer_seller_id)
                ->map(fn (Collection $items, int|string $sellerId): Order => $this->createOrder(
                    $buyer,
                    $items,
                    $preference,
                    $fulfillmentNote,
                    $choices[(int) $sellerId] ?? PaymentMethod::CashOnHandover,
                ))
                ->values();

            CartItem::query()->where('buyer_id', $buyer->id)->delete();

            return $orders;
        });

        foreach ($orders as $order) {
            if ($order->payment_method === PaymentMethod::OnlineTransfer->value) {
                $this->askSellerForQr($buyer, $order);
            }
        }

        return $orders;
    }

    /**
     * @param  Collection<int, CartItem>  $items
     */
    private function createOrder(
        User $buyer,
        Collection $items,
        FulfillmentPreference $preference,
        ?string $fulfillmentNote,
        PaymentMethod $paymentMethod,
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
            paymentMethod: $paymentMethod,
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
        PaymentMethod $paymentMethod = PaymentMethod::CashOnHandover,
    ): Order {
        $subtotal = array_sum(array_column($lines, 'line_subtotal'));
        $tawadTotal = array_sum(array_column($lines, 'tawad_amount'));

        if ($reservationId !== null) {
            $paymentMethod = PaymentMethod::CashOnHandover;
        }

        $order = Order::create([
            'order_number' => $this->orderNumbers->generate(),
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $sellerId,
            'farm_id' => $farmId,
            'status' => OrderStatus::Placed,
            'fulfillment_preference' => $preference,
            'fulfillment_note' => $fulfillmentNote,
            'payment_method' => $paymentMethod->value,
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
     * Sellers not listed stay on cash. Online payment is refused when the
     * seller has turned it off. Nothing here moves money.
     *
     * @param  Collection<int, CartItem>  $cartItems
     * @param  array<int, array{seller_id?: int, method?: string}>  $payments
     * @return array<int, PaymentMethod>
     */
    private function paymentChoices(Collection $cartItems, array $payments): array
    {
        $requested = [];

        foreach ($payments as $payment) {
            $sellerId = (int) ($payment['seller_id'] ?? 0);
            $method = PaymentMethod::tryFrom((string) ($payment['method'] ?? ''));

            if ($sellerId > 0 && $method !== null) {
                $requested[$sellerId] = $method;
            }
        }

        $choices = [];

        foreach ($cartItems->groupBy(fn (CartItem $item): int => (int) $item->listing->farmer_seller_id) as $sellerId => $items) {
            $sellerId = (int) $sellerId;
            $method = $requested[$sellerId] ?? PaymentMethod::CashOnHandover;
            $seller = $items->first()->listing->farmerSeller;

            if ($method === PaymentMethod::OnlineTransfer && $seller?->acceptsOnlinePayment() !== true) {
                $shop = $seller?->shop_name ?: $seller?->name ?: 'This seller';

                throw ValidationException::withMessages([
                    'payments' => "{$shop} accepts cash only.",
                ]);
            }

            $choices[$sellerId] = $method;
        }

        return $choices;
    }

    /**
     * One note from the buyer, tagged to this order. A chat failure does not
     * undo the order.
     */
    private function askSellerForQr(User $buyer, Order $order): void
    {
        try {
            $conversation = StallConversation::query()->firstOrCreate([
                'buyer_id' => $buyer->id,
                'farmer_seller_id' => $order->farmer_seller_id,
            ]);

            $total = number_format((float) $order->total, 2, '.', '');

            app(SendStallMessage::class)->handle($buyer, $conversation, [
                'body' => "Hi! I chose online payment for Order #{$order->order_number} (₱{$total}). Please send your GCash/Maya QR here.",
                'order_id' => $order->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
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

        if (! $listing->allowsOrderQuantity($quantity)) {
            throw ValidationException::withMessages([
                'cart' => "{$listing->title}: {$listing->orderQuantityMessage()}",
            ]);
        }

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
