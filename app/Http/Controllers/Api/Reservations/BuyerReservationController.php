<?php

namespace App\Http\Controllers\Api\Reservations;

use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\ReserveListing;
use App\Enums\FulfillmentPreference;
use App\Enums\OrderPaymentStatus;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reservations\StoreReservationRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BuyerReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = Reservation::query()
            ->where('buyer_id', $request->user()->id)
            ->with('latestProof.paymentQr')
            ->orderByRaw('case when status = ? then 0 else 1 end', [ReservationStatus::Active->value])
            ->orderByDesc('id')
            ->get();

        return ReservationResource::collection($reservations);
    }

    public function store(StoreReservationRequest $request, ReserveListing $reserve): JsonResponse
    {
        $result = $reserve->handle(
            $request->user(),
            (int) $request->validated('listing_id'),
            (float) $request->validated('quantity'),
            FulfillmentPreference::from($request->validated('fulfillment_preference')),
            $request->validated('fulfillment_note'),
            $request->input('payment_flow'),
        );

        $message = $result['created'] ? 'Reserved.' : 'Reservation updated.';

        return (new ReservationResource($result['reservation']))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    public function cancel(Request $request, Reservation $reservation, CancelReservation $cancel): JsonResponse
    {
        $this->authorize('cancel', $reservation);

        if ($reservation->payment_status !== null && ! in_array($reservation->payment_status, [
            OrderPaymentStatus::AwaitingPayment,
            OrderPaymentStatus::NotTracked,
        ], true)) {
            throw ValidationException::withMessages([
                'reservation' => "You've already paid for this reservation. Message the seller to change it.",
            ]);
        }

        $cancel->handle(
            $reservation,
            ReservationCancellationReason::BuyerCancelled,
            notifyBuyer: false,
        );

        return response()->json(['message' => 'Reservation cancelled.']);
    }
}
