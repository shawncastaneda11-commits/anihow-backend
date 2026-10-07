<?php

namespace App\Http\Controllers\Api\Reservations;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\OpenListingNow;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Api\Listings\ListingController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reservations\CancelReservationRequest;
use App\Http\Resources\Api\ListingResource;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Listing;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerReservationController extends Controller
{
    public function index(Listing $listing): AnonymousResourceCollection
    {
        $this->authorize('view', $listing);

        $reservations = $listing->reservations()
            ->where('status', ReservationStatus::Active)
            ->with('buyer')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return ReservationResource::collection($reservations);
    }

    public function cancel(
        CancelReservationRequest $request,
        Listing $listing,
        Reservation $reservation,
        CancelReservation $cancel,
    ): JsonResponse {
        if ((int) $reservation->listing_id !== (int) $listing->id) {
            abort(404);
        }

        $cancel->handle(
            $reservation,
            ReservationCancellationReason::SellerCancelled,
            $request->validated('note'),
        );

        return response()->json(['message' => 'Reservation cancelled.']);
    }

    public function open(Listing $listing, OpenListingNow $open): ListingResource
    {
        $this->authorize('update', $listing);

        $open->handle($listing);

        $listing = Listing::query()
            ->with(ListingController::relations())
            ->withActiveReservationTotals()
            ->findOrFail($listing->id);

        return (new ListingResource($listing))
            ->additional(['message' => 'Listing opened.']);
    }
}
