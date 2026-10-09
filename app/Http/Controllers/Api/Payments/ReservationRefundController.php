<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\RefundReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\RefundReservationRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;

class ReservationRefundController extends Controller
{
    public function __invoke(
        RefundReservationRequest $request,
        Reservation $reservation,
        RefundReservationAction $refund,
    ): ReservationResource {
        $reservation = $refund->handle(
            $request->user(),
            $reservation,
            $request->validated('refund_reference'),
        );

        $reservation->load(['buyer', 'latestProof.paymentQr']);

        return (new ReservationResource($reservation))->additional(['message' => 'Refund recorded.']);
    }
}
