<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\CloseUnreceivedPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\MarkOrderPaymentNotReceivedRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;

class OrderPaymentNotReceivedController extends Controller
{
    public function __invoke(
        MarkOrderPaymentNotReceivedRequest $request,
        Order $order,
        CloseUnreceivedPaymentAction $close,
    ): OrderResource {
        $order = $close->forOrder($request->user(), $order);
        $order->load(['items', 'buyer', 'farm', 'latestProof.paymentQr']);

        return (new OrderResource($order))->additional(['message' => 'Marked as never received.']);
    }
}
