<?php

namespace App\Http\Controllers\Api\Orders;

use App\Enums\FulfillmentPreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\CheckoutRequest;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    /**
     * One cart becomes one order per farmer-seller, so this returns a
     * collection even when the buyer thinks they placed a single order.
     */
    public function __invoke(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $this->authorize('create', Order::class);

        $orders = $checkout->checkout(
            $request->user(),
            FulfillmentPreference::from($request->validated('fulfillment_preference')),
            $request->validated('fulfillment_note'),
        );

        $orders->each->load(['items', 'farmerSeller', 'farm']);

        return OrderResource::collection($orders)
            ->additional([
                'message' => $orders->count() === 1
                    ? 'Order placed.'
                    : "Your cart was split into {$orders->count()} orders, one per seller.",
            ])
            ->response()
            ->setStatusCode(201);
    }
}
