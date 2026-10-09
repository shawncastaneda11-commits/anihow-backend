<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\SubmitReservationPaymentProofAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\StoreReservationPaymentProofRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\Reservation;

class BuyerReservationPaymentProofController extends Controller
{
    public function store(
        StoreReservationPaymentProofRequest $request,
        Reservation $reservation,
        SubmitReservationPaymentProofAction $submit,
    ): ReservationResource {
        $submit->handle(
            $request->user(),
            $reservation,
            $request->validated('reference_number'),
            $request->validated('amount'),
            (int) $request->validated('qr_id'),
            $request->file('screenshot'),
        );

        $reservation->refresh()->load(['buyer', 'latestProof.paymentQr']);

        return (new ReservationResource($reservation))->additional(['message' => 'Payment proof sent.']);
    }
}
