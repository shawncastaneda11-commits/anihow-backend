<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\RefundOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payments\RefundOrderRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;

class OrderRefundController extends Controller
{
    public function __invoke(RefundOrderRequest $request, Order $order, RefundOrderAction $refund): OrderResource
    {
        $order = $refund->handle($request->user(), $order, $request->validated('refund_reference'));
        $order->load(['items', 'buyer', 'farm', 'latestProof.paymentQr']);

        return (new OrderResource($order))->additional(['message' => 'Refund recorded.']);
    }
}
