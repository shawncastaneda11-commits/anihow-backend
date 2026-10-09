<?php

namespace App\Actions\Reservations;

use App\Actions\Payments\RecordPaymentEvent;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderPaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\SellerPaymentQr;
use App\Models\User;
use App\Services\OrderLinePricer;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveListing
{
    public const int ActiveCap = 3;

    public function __construct(
        private readonly OrderLinePricer $pricer,
        private readonly InAppNotifier $notifier,
        private readonly RecordPaymentEvent $events,
    ) {}

    /**
     * Cash-only demo rows pass $asNotTracked. A buyer request never does:
     * those require the seller's QR and payment_flow proof.
     *
     * @return array{reservation: Reservation, created: bool}
     */
    public function handle(
        User $buyer,
        int $listingId,
        float $quantity,
        FulfillmentPreference $preference,
        ?string $note,
        ?string $paymentFlow = null,
        bool $asNotTracked = false,
    ): array {
        return DB::transaction(function () use ($buyer, $listingId, $quantity, $preference, $note, $paymentFlow, $asNotTracked): array {
            // Lock the buyer before counting so two reserves cannot both pass the cap of 3.
            User::query()->whereKey($buyer->id)->lockForUpdate()->first();

            $listing = Listing::query()
                ->whereKey($listingId)
                ->with(['cropType', 'activeTawadRule', 'farm.cropTypeOverrides', 'farmerSeller'])
                ->lockForUpdate()
                ->first();

            if ($listing === null || ! Listing::query()->listedForBuyers()->whereKey($listing->id)->exists()) {
                abort(404);
            }

            $existing = Reservation::query()
                ->where('buyer_id', $buyer->id)
                ->where('listing_id', $listing->id)
                ->where('status', ReservationStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($existing === null && ! Listing::query()->allowedByFarmFeatures()->whereKey($listing->id)->exists()) {
                throw ValidationException::withMessages([
                    'listing_id' => "{$listing->title} is no longer available.",
                ]);
            }

            if ($existing === null && $listing->farm !== null && ! $listing->farm->allowsReservations()) {
                throw ValidationException::withMessages([
                    'listing_id' => Farm::RESERVATIONS_OFF_MESSAGE,
                ]);
            }

            if (! $listing->isUpcoming()) {
                throw ValidationException::withMessages([
                    'listing_id' => "{$listing->title} is not available to reserve.",
                ]);
            }

            $seller = $listing->farmerSeller;

            if (! $asNotTracked && $existing === null) {
                $hasQr = $seller !== null && $seller->paymentQrs()->exists();

                if ($seller?->acceptsOnlinePayment() !== true || ! $hasQr) {
                    $shop = $seller?->shop_name ?: $seller?->name ?: 'This shop';

                    throw ValidationException::withMessages([
                        'listing_id' => "{$shop} doesn't take reservations yet.",
                    ]);
                }
            }

            if (! $asNotTracked && $paymentFlow !== 'proof') {
                throw ValidationException::withMessages([
                    'payment_flow' => 'Update AniHow to reserve.',
                ]);
            }

            if ($existing !== null && in_array($existing->payment_status, [
                OrderPaymentStatus::PaymentSent,
                OrderPaymentStatus::Paid,
                OrderPaymentStatus::RefundDue,
                OrderPaymentStatus::Refunded,
            ], true)) {
                throw ValidationException::withMessages([
                    'listing_id' => "You've already paid for this reservation. Message the seller to change it.",
                ]);
            }

            if ($listing->available_from === null || ! $listing->available_from->greaterThan(now()->addHour())) {
                throw ValidationException::withMessages([
                    'listing_id' => 'Reservations closed. You can order once it opens.',
                ]);
            }

            if ($existing === null) {
                $activeCount = Reservation::query()
                    ->where('buyer_id', $buyer->id)
                    ->where('status', ReservationStatus::Active)
                    ->count();

                if ($activeCount >= self::ActiveCap) {
                    throw ValidationException::withMessages([
                        'listing_id' => 'You can have at most 3 active reservations.',
                    ]);
                }
            }

            $reservedByOthers = (float) Reservation::query()
                ->where('listing_id', $listing->id)
                ->where('status', ReservationStatus::Active)
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))
                ->sum('quantity');

            $remaining = (float) $listing->quantity_available - $reservedByOthers;

            if ($quantity > $remaining) {
                $available = number_format(max(0, $remaining), 2, '.', '');

                throw ValidationException::withMessages([
                    'quantity' => "Only {$available} available.",
                ]);
            }

            $left = min($listing->sellableQuantity(), $remaining);

            if (Listing::orderHundredths($left) < Listing::orderHundredths((float) $listing->min_order_quantity)) {
                throw ValidationException::withMessages([
                    'quantity' => $listing->belowMinimumStockMessage($left),
                ]);
            }

            if (! $listing->allowsOrderQuantity($quantity)) {
                throw ValidationException::withMessages([
                    'quantity' => $listing->orderQuantityMessage(),
                ]);
            }

            $priced = $this->pricer->price(
                $listing,
                $quantity,
                'listing_id',
                "{$listing->title} is priced below the current floor price and cannot be reserved.",
            );

            $attributes = [
                'quantity' => $priced['quantity'],
                'unit' => $priced['unit'],
                'unit_price' => $priced['unit_price'],
                'line_subtotal' => $priced['line_subtotal'],
                'tawad_amount' => $priced['tawad_amount'],
                'line_total' => $priced['line_total'],
                'crop_type_id' => $priced['crop_type_id'],
                'listing_name' => $priced['listing_name'],
                'tawad_rule_id' => $priced['tawad_rule_id'],
                'tawad_type' => $priced['tawad_type'],
                'fulfillment_preference' => $preference,
                'fulfillment_note' => $note,
                'active_slot' => "{$buyer->id}:{$listing->id}",
            ];

            if ($existing !== null) {
                $existing->update($attributes);

                return [
                    'reservation' => $existing->refresh(),
                    'created' => false,
                ];
            }

            $payment = [
                'payment_status' => OrderPaymentStatus::NotTracked,
            ];

            if (! $asNotTracked) {
                $hours = (int) ($seller?->payment_time_limit_hours ?: 24);
                $limit = now()->addHours($hours);
                $opening = $listing->available_from;
                $due = $opening->lessThan($limit) ? $opening->copy() : $limit;
                $qrIds = SellerPaymentQr::query()
                    ->where('farmer_seller_id', $listing->farmer_seller_id)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();
                $payment = [
                    'payment_status' => OrderPaymentStatus::AwaitingPayment,
                    'payment_due_at' => $due,
                    'payment_qr_ids' => $qrIds,
                ];
            }

            $reservation = Reservation::query()->create([
                'buyer_id' => $buyer->id,
                'listing_id' => $listing->id,
                'farmer_seller_id' => $listing->farmer_seller_id,
                'farm_id' => $listing->farm_id,
                'status' => ReservationStatus::Active,
                ...$payment,
                ...$attributes,
            ]);

            if (! $asNotTracked) {
                $this->events->forReservation($reservation, 'reserved', $buyer);
            }

            if ($seller !== null) {
                $this->notifier->reservationMade($seller, $reservation);
            }

            return [
                'reservation' => $reservation,
                'created' => true,
            ];
        });
    }
}
