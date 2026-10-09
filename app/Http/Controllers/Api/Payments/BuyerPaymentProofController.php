<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\SubmitPaymentProofAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\StorePaymentProofRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;

class BuyerPaymentProofController extends Controller
{
    public function store(
        StorePaymentProofRequest $request,
        Order $order,
        SubmitPaymentProofAction $submit,
    ): OrderResource {
        $submit->handle(
            $request->user(),
            $order,
            $request->validated('reference_number'),
            $request->validated('amount'),
            (int) $request->validated('qr_id'),
            $request->file('screenshot'),
        );

        $order->refresh()->load(['items', 'farmerSeller', 'farm', 'latestProof.paymentQr']);

        return (new OrderResource($order))->additional(['message' => 'Payment proof sent.']);
    }
}
