<?php

namespace App\Http\Controllers\Api\Chat;

use App\Actions\Chat\SendStallMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chat\StoreOrderMessageRequest;
use App\Http\Resources\Api\OrderMessageResource;
use App\Models\Order;
use App\Models\StallConversation;
use App\Models\StallMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderMessageController extends Controller
{
    public function index(Request $request, Order $order): AnonymousResourceCollection
    {
        $this->authorize('chat', $order);

        $messages = StallMessage::query()
            ->where('order_id', $order->id)
            ->with('author.roles')
            ->when(
                $request->filled('after_id'),
                fn ($query) => $query->where('id', '>', (int) $request->integer('after_id')),
            )
            ->orderBy('id')
            ->get();

        return OrderMessageResource::collection($messages);
    }

    public function store(
        StoreOrderMessageRequest $request,
        Order $order,
        SendStallMessage $sendStallMessage,
    ): JsonResponse {
        $conversation = StallConversation::query()->firstOrCreate([
            'buyer_id' => $order->buyer_id,
            'farmer_seller_id' => $order->farmer_seller_id,
        ]);

        $message = $sendStallMessage->handle($request->user(), $conversation, [
            'body' => $request->validated('body'),
            'order_id' => $order->id,
        ]);

        return (new OrderMessageResource($message))
            ->additional(['message' => 'Message sent.'])
            ->response()
            ->setStatusCode(201);
    }
}
