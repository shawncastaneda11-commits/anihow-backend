<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\ReviewPaymentProofAction;
use App\Enums\PaymentRejectionReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\ReviewPaymentProofRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Models\PaymentProof;

class FarmerPaymentProofController extends Controller
{
    public function update(
        ReviewPaymentProofRequest $request,
        Order $order,
        PaymentProof $paymentProof,
        ReviewPaymentProofAction $review,
    ): OrderResource {
        abort_unless($paymentProof->order_id === $order->id, 404);

        $reason = $request->validated('reason');

        $order = $review->handle(
            $request->user(),
            $order,
            $paymentProof,
            $request->validated('decision'),
            $reason !== null ? PaymentRejectionReason::from($reason) : null,
            $request->validated('note'),
        );

        $order->load(['items', 'buyer', 'farm', 'latestProof.paymentQr']);

        return (new OrderResource($order))->additional(['message' => 'Payment reviewed.']);
    }
}
