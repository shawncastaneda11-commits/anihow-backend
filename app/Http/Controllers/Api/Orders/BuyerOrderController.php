<?php

namespace App\Http\Controllers\Api\Orders;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\CancelOrderRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = $request->user()
            ->orders()
            ->with(['items', 'farmerSeller', 'farm'])
            ->latest()
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        $order->load(['items', 'farmerSeller', 'farm', 'statusHistories', 'review']);

        return new OrderResource($order);
    }

    /**
     * A buyer may cancel only while the order is Placed. Once the seller
     * confirms, stock is committed and picking may have started.
     */
    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        OrderStateMachine $stateMachine,
    ): OrderResource {
        $order = $stateMachine->transition(
            order: $order,
            next: OrderStatus::Cancelled,
            actor: $request->user(),
            note: $request->validated('note'),
            reason: CancellationReason::BuyerCancelled,
        );

        $order->load(['items', 'farmerSeller', 'farm']);

        return (new OrderResource($order))
            ->additional(['message' => 'Order cancelled.']);
    }
}
