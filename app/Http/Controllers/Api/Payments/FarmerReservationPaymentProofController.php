<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\ReviewReservationPaymentProofAction;
use App\Enums\PaymentRejectionReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\ReviewReservationPaymentProofRequest;
use App\Http\Resources\Api\ReservationResource;
use App\Models\PaymentProof;
use App\Models\Reservation;

class FarmerReservationPaymentProofController extends Controller
{
    public function update(
        ReviewReservationPaymentProofRequest $request,
        Reservation $reservation,
        PaymentProof $paymentProof,
        ReviewReservationPaymentProofAction $review,
    ): ReservationResource {
        abort_unless((int) $paymentProof->reservation_id === (int) $reservation->id, 404);

        $reason = $request->validated('reason');

        $reservation = $review->handle(
            $request->user(),
            $reservation,
            $paymentProof,
            $request->validated('decision'),
            $reason !== null ? PaymentRejectionReason::from($reason) : null,
            $request->validated('note'),
        );

        $reservation->load(['buyer', 'latestProof.paymentQr']);

        return (new ReservationResource($reservation))->additional(['message' => 'Payment reviewed.']);
    }
}
