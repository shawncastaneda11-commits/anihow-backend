<?php

namespace App\Http\Controllers\Api\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrderResource;
use App\Http\Resources\Api\ReceiptResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderHistoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = $request->user()
            ->orders()
            ->whereIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->with(['items', 'farmerSeller', 'farm', 'review'])
            ->latest()
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function receipt(Request $request, Order $order): ReceiptResource
    {
        $this->authorize('view', $order);

        abort_unless($order->isOwnedByBuyer($request->user()), 403);
        abort_unless($order->status === OrderStatus::Completed, 404);

        $order->load(['items', 'farmerSeller', 'farm']);

        return new ReceiptResource($order);
    }
}
