<?php

namespace App\Http\Controllers\Api\Orders;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\CancelOrderRequest;
use App\Http\Requests\Api\Orders\CompleteOrderRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = $request->user()
            ->incomingOrders()
            ->with(['items', 'buyer'])
            ->when(
                $request->query('status'),
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        $order->load(['items', 'buyer', 'statusHistories', 'review']);

        return new OrderResource($order);
    }

    /**
     * Stock is deducted here and prices freeze at this point.
     */
    public function confirm(Request $request, Order $order, OrderStateMachine $stateMachine): OrderResource
    {
        $this->authorize('advance', $order);

        return $this->respond(
            $stateMachine->transition($order, OrderStatus::Confirmed, $request->user()),
            'Order confirmed.',
        );
    }

    public function ready(Request $request, Order $order, OrderStateMachine $stateMachine): OrderResource
    {
        $this->authorize('advance', $order);

        return $this->respond(
            $stateMachine->transition($order, OrderStatus::Ready, $request->user()),
            'Order marked ready.',
        );
    }

    /**
     * Handover happened. The cash figure is recorded, never processed.
     */
    public function complete(
        CompleteOrderRequest $request,
        Order $order,
        OrderStateMachine $stateMachine,
    ): OrderResource {
        return $this->respond(
            $stateMachine->transition(
                order: $order,
                next: OrderStatus::Completed,
                actor: $request->user(),
                amountReceived: (float) $request->validated('amount_received'),
            ),
            'Order completed.',
        );
    }

    /**
     * Covers a decline and a no-show. Both are cancellations with a reason.
     */
    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        OrderStateMachine $stateMachine,
    ): OrderResource {
        return $this->respond(
            $stateMachine->transition(
                order: $order,
                next: OrderStatus::Cancelled,
                actor: $request->user(),
                note: $request->validated('note'),
                reason: CancellationReason::from($request->validated('reason')),
            ),
            'Order cancelled.',
        );
    }

    private function respond(Order $order, string $message): OrderResource
    {
        $order->load(['items', 'buyer']);

        return (new OrderResource($order))->additional(['message' => $message]);
    }
}
