<?php

namespace App\Actions\Listings;

use App\Enums\CancellationReason;
use App\Enums\OrderActor;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A takedown overrides the seller, but not silently. Placed orders still
 * hold stock on the listing, so they are cancelled with it; Confirmed and
 * Ready orders were already promised to the buyer and are left to finish.
 */
class GuardListingOpenOrders
{
    public const CANCELLATION_NOTE = 'Cancelled because the listing was taken down.';

    public function __construct(private OrderStateMachine $stateMachine) {}

    /**
     * @return array{placed: int, placed_paid: int, in_progress: int}|null
     */
    public function describe(Listing $listing): ?array
    {
        $orders = $this->openOrders($listing)->get(['id', 'status', 'payment_status']);

        if ($orders->isEmpty()) {
            return null;
        }

        $placed = $orders->filter(fn (Order $order): bool => $order->status === OrderStatus::Placed);

        return [
            'placed' => $placed->count(),
            'placed_paid' => $placed->filter(fn (Order $order): bool => in_array(
                $order->payment_status,
                [OrderPaymentStatus::PaymentSent, OrderPaymentStatus::Paid],
                true,
            ))->count(),
            'in_progress' => $orders->count() - $placed->count(),
        ];
    }

    public function warning(Listing $listing): ?string
    {
        $summary = $this->describe($listing);

        if ($summary === null) {
            return null;
        }

        $parts = [];

        if ($summary['placed'] > 0) {
            $part = "{$summary['placed']} placed order(s) will be cancelled and the buyers notified";

            if ($summary['placed_paid'] > 0) {
                $part .= " ({$summary['placed_paid']} already paid online, so the seller will owe a refund)";
            }

            $parts[] = $part;
        }

        if ($summary['in_progress'] > 0) {
            $parts[] = "{$summary['in_progress']} confirmed or ready order(s) stay open so the seller can finish them";
        }

        return 'This listing has open orders: '.implode('; ', $parts).'.';
    }

    public function ensure(Listing $listing, bool $confirmed): void
    {
        if ($confirmed) {
            return;
        }

        $warning = $this->warning($listing);

        if ($warning !== null) {
            throw ValidationException::withMessages([
                'confirm_open_orders' => $warning,
            ]);
        }
    }

    /**
     * Cancel every Placed app order that includes this listing, releasing the
     * stock it held. Returns how many were cancelled.
     */
    public function cancelPlaced(Listing $listing): int
    {
        $cancelled = 0;

        $this->openOrders($listing)
            ->where('status', OrderStatus::Placed)
            ->where('source', OrderSource::App)
            ->orderBy('id')
            ->each(function (Order $order) use (&$cancelled): void {
                try {
                    $this->stateMachine->transition(
                        $order,
                        OrderStatus::Cancelled,
                        OrderActor::System,
                        note: self::CANCELLATION_NOTE,
                        reason: CancellationReason::ListingTakenDown,
                    );
                } catch (ValidationException) {
                    Log::info('Skipping order that changed state during a takedown.', [
                        'order_id' => $order->id,
                    ]);

                    return;
                }

                $cancelled++;
            });

        return $cancelled;
    }

    /**
     * @return Builder<Order>
     */
    private function openOrders(Listing $listing): Builder
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Placed, OrderStatus::Confirmed, OrderStatus::Ready])
            ->whereHas('items', fn (Builder $query) => $query->where('listing_id', $listing->id));
    }
}
