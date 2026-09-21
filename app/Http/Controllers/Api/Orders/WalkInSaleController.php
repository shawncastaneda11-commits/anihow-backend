<?php

namespace App\Http\Controllers\Api\Orders;

use App\Actions\Orders\RecordWalkInSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Orders\RecordWalkInSaleRequest;
use App\Http\Resources\Api\OrderResource;
use Illuminate\Http\JsonResponse;

/**
 * The farmer-seller records an in-person sale to someone without the app.
 * Lives in the Android application, not the CMS, because the farmer-seller is
 * the one holding the cash.
 */
class WalkInSaleController extends Controller
{
    public function __invoke(RecordWalkInSaleRequest $request, RecordWalkInSaleAction $action): JsonResponse
    {
        $order = $action->execute(
            seller: $request->user(),
            listing: $request->listing(),
            quantity: (float) $request->validated('quantity'),
            amountReceived: (float) $request->validated('amount_received'),
            buyerName: $request->validated('buyer_name'),
            note: $request->validated('note'),
        );

        return (new OrderResource($order))
            ->additional(['message' => 'Walk-in sale recorded.'])
            ->response()
            ->setStatusCode(201);
    }
}
