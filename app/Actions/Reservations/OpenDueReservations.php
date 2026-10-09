<?php

namespace App\Actions\Reservations;

use App\Actions\Listings\EnsureHarvestRecorded;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderPaymentStatus;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Services\CheckoutService;
use App\Support\InAppNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Turns due reservations into app orders, or cancels them when the harvest
 * can no longer be sold. Callers that take stock must run forListing first,
 * outside their own transaction, so a reservation is holding stock before a
 * cart, a checkout, or a walk-in asks for the same listing.
 */
class OpenDueReservations
{
    public function __construct(
        private readonly CancelReservation $canceller,
        private readonly EnsureHarvestRecorded $ensureHarvest,
    ) {}

    public function handle(): void
    {
        $listingIds = Reservation::query()
            ->where('status', ReservationStatus::Active)
            ->whereNotNull('listing_id')
            ->distinct()
            ->pluck('listing_id');

        foreach ($listingIds as $listingId) {
            $this->forListing((int) $listingId);
        }

        Reservation::query()
            ->where('status', ReservationStatus::Active)
            ->whereNull('listing_id')
            ->orderBy('id')
            ->each(fn (Reservation $reservation) => $this->canceller->handle(
                $reservation,
                ReservationCancellationReason::ListingRemoved,
            ));
    }

    public function forListing(int $listingId): void
    {
        $hasActive = Reservation::query()
            ->where('listing_id', $listingId)
            ->where('status', ReservationStatus::Active)
            ->exists();

        if (! $hasActive) {
            return;
        }

        $listing = Listing::withTrashed()->with('farmerSeller')->find($listingId);

        if ($listing !== null && ! $this->mustRemove($listing) && ! $this->windowHasOpened($listing)) {
            return;
        }

        DB::transaction(function () use ($listingId): void {
            $listing = Listing::withTrashed()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->first();

            $listing?->load('farmerSeller');

            $reservations = Reservation::query()
                ->where('listing_id', $listingId)
                ->where('status', ReservationStatus::Active)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return;
            }

            if ($listing === null || $this->mustRemove($listing)) {
                foreach ($reservations as $reservation) {
                    $this->canceller->handle($reservation, ReservationCancellationReason::ListingRemoved);
                }

                return;
            }

            if (! $this->windowHasOpened($listing)) {
                return;
            }

            $this->ensureHarvest->forListing($listing);

            $spokenFor = 0.0;

            foreach ($reservations as $reservation) {
                $listing->refresh();
                $needed = round((float) $reservation->quantity, 2);
                $sellable = round($listing->sellableQuantity() - $spokenFor, 2);
                $payment = $reservation->payment_status;

                if ($payment === OrderPaymentStatus::PaymentSent) {
                    if ($sellable + 0.001 < $needed) {
                        $this->canceller->handle($reservation, ReservationCancellationReason::HarvestShortfall);
                    } else {
                        $spokenFor += $needed;
                    }

                    continue;
                }

                if ($payment === OrderPaymentStatus::AwaitingPayment) {
                    $deadline = $this->openingDeadline($reservation, $listing);

                    if ($deadline !== null && ! now()->greaterThan($deadline) && $sellable + 0.001 >= $needed) {
                        $spokenFor += $needed;

                        continue;
                    }

                    $this->canceller->handle(
                        $reservation,
                        $deadline !== null && ! now()->greaterThan($deadline)
                            ? ReservationCancellationReason::HarvestShortfall
                            : ReservationCancellationReason::PaymentExpired,
                    );

                    continue;
                }

                if ($sellable + 0.001 < $needed) {
                    $this->canceller->handle($reservation, ReservationCancellationReason::HarvestShortfall);

                    continue;
                }

                $this->convert($reservation);
            }
        });
    }

    /**
     * An unpaid reservation can still be paid after opening only while its
     * deadline holds, and never past an hour after opening. That covers a
     * proof rejected near harvest day and a listing the seller opened early.
     * A shortened deadline is saved so the buyer sees the real time.
     */
    private function openingDeadline(Reservation $reservation, Listing $listing): ?CarbonInterface
    {
        $deadline = $reservation->payment_due_at;
        $graceEnds = $listing->available_from?->copy()->addHour();

        if ($graceEnds !== null && ($deadline === null || $deadline->greaterThan($graceEnds))) {
            $deadline = $graceEnds;
            $reservation->forceFill(['payment_due_at' => $deadline])->save();
        }

        return $deadline;
    }

    private function convert(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Active || $reservation->farm_id === null) {
            $this->canceller->handle($reservation, ReservationCancellationReason::ListingRemoved);

            return;
        }

        $buyer = $reservation->buyer;

        if ($buyer === null || ! $buyer->isActive()) {
            $this->canceller->handle($reservation, ReservationCancellationReason::AccountClosed);

            return;
        }

        // The price was locked at reserve time. A later floor change does not rewrite it.
        $line = $reservation->storedLine();

        $order = app(CheckoutService::class)->placeAppOrder(
            $buyer,
            (int) $reservation->farmer_seller_id,
            (int) $reservation->farm_id,
            [$line],
            $reservation->fulfillment_preference ?? FulfillmentPreference::BuyerPickup,
            $reservation->fulfillment_note,
            $reservation->id,
        );

        $reservation->update([
            'status' => ReservationStatus::Converted,
            'order_id' => $order->id,
            'converted_at' => now(),
            'active_slot' => null,
        ]);

        app(InAppNotifier::class)->reservationConverted($buyer, $order);
    }

    private function mustRemove(Listing $listing): bool
    {
        if ($listing->trashed() || $listing->isExpired()) {
            return true;
        }

        return ! Listing::query()->listedForBuyers()->whereKey($listing->id)->exists();
    }

    private function windowHasOpened(Listing $listing): bool
    {
        if ($listing->isExpired()) {
            return false;
        }

        return $listing->available_from === null || ! $listing->available_from->isFuture();
    }
}
