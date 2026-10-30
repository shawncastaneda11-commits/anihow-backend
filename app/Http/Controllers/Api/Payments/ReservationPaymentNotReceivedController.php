<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\CloseUnreceivedPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\MarkReservationPaymentNotReceivedRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;

class ReservationPaymentNotReceivedController extends Controller
{
    public function __invoke(
        MarkReservationPaymentNotReceivedRequest $request,
        Reservation $reservation,
        CloseUnreceivedPaymentAction $close,
    ): ReservationResource {
        $reservation = $close->forReservation($request->user(), $reservation);
        $reservation->load(['buyer', 'latestProof.paymentQr']);

        return (new ReservationResource($reservation))->additional(['message' => 'Marked as never received.']);
    }
}
