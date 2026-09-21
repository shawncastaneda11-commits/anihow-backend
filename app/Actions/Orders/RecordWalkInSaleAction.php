<?php

namespace App\Actions\Orders;

use App\Enums\FulfillmentPreference;
use App\Enums\ListingStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\OrderLinePricer;
use App\Services\OrderNumberGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a walk-in sale: an in-person sale by a farmer-seller to someone with
 * no buyer account, entered after the handover has already happened.
 *
 * It lands in the same orders table as every app order, so the descriptive
 * summaries read it with no separate query. A second way into one ledger, not
 * a second sales surface and not a sixth module.
 *
 * How it differs from an app order, and nowhere else:
 *
 *   Created directly at Completed. The handover is over, so there is no
 *   Placed, Confirmed, or Ready to pass through.
 *
 *   Stock is deducted at once from quantity_available. The one exception to
 *   stock-at-Confirmed, checked against sellable quantity so stock held for
 *   app orders is never sold twice. Decision 23.
 *
 *   No buyer account, so no review. Decision 19.
 *
 * What stays the same: one listing per record (decision 21), priced by
 * OrderLinePricer on the farm's effective floor and ceiling (decisions 24 and
 * 29), amount received required (decision 26), fulfillment recorded as buyer
 * pickup (decision 27).
 */
class RecordWalkInSaleAction
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly OrderLinePricer $pricer,
    ) {}

    /**
     * @throws AuthorizationException when the listing is not the seller's
     * @throws ValidationException
     */
    public function execute(
        User $seller,
        Listing $listing,
        float $quantity,
        float $amountReceived,
        ?string $buyerName = null,
        ?string $note = null,
    ): Order {
        return DB::transaction(function () use ($seller, $listing, $quantity, $amountReceived, $buyerName, $note): Order {
            $listing = Listing::query()
                ->whereKey($listing->getKey())
                ->with(['cropType', 'activeTawadRule', 'farm.cropTypeOverrides'])
                ->lockForUpdate()
                ->firstOrFail();

            // The request authorizes this too. Kept here so no other caller can
            // record a sale against someone else's listing.
            if (! $listing->isOwnedBy($seller)) {
                throw new AuthorizationException('You can only record sales of your own listings.');
            }

            // A paused listing may still sell at the stall; a taken-down one is
            // a moderation decision and cannot be worked around. Decision 25.
            if ($listing->status === ListingStatus::TakenDown) {
                throw ValidationException::withMessages([
                    'listing_id' => "{$listing->title} was taken down and cannot record sales.",
                ]);
            }

            if ($amountReceived < 0) {
                throw ValidationException::withMessages([
                    'amount_received' => 'Amount received cannot be negative.',
                ]);
            }

            if (! $listing->hasStockFor($quantity)) {
                $sellable = number_format($listing->sellableQuantity(), 2, '.', '');

                throw ValidationException::withMessages([
                    'quantity' => "{$listing->title} has only {$sellable} available to sell.",
                ]);
            }

            $line = $this->pricer->price(
                $listing,
                $quantity,
                'listing_id',
                "{$listing->title} is priced below the current floor price. Update the price before recording a sale.",
            );

            $now = now();

            $order = Order::create([
                'order_number' => $this->orderNumbers->generate(),
                'buyer_id' => null,
                'farmer_seller_id' => $seller->id,
                'farm_id' => $listing->farm_id,
                'status' => OrderStatus::Completed,
                'source' => OrderSource::WalkIn,
                'walk_in_buyer_name' => filled($buyerName) ? trim($buyerName) : null,
                'fulfillment_preference' => FulfillmentPreference::BuyerPickup,
                'fulfillment_note' => null,
                'payment_method' => 'cash_on_handover',
                'subtotal' => $line['line_subtotal'],
                'tawad_total' => $line['tawad_amount'],
                'total' => $line['line_total'],
                'amount_received' => $amountReceived,
                'notes' => filled($note) ? trim($note) : null,
                'confirmed_at' => $now,
                'completed_at' => $now,
            ]);

            $order->items()->create($line);

            $listing->quantity_available = (float) $listing->quantity_available - $quantity;
            $listing->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::Completed,
                'changed_by' => $seller->id,
                'note' => 'Walk-in sale recorded.',
            ]);

            return $order->load('items');
        });
    }
}
