<?php

namespace App\Http\Controllers\Api\Chat;

use App\Events\OrderMessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chat\StoreOrderMessageRequest;
use App\Http\Resources\Api\OrderMessageResource;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderMessageController extends Controller
{
    public function index(Request $request, Order $order): AnonymousResourceCollection
    {
        $this->authorize('chat', $order);

        $messages = $order->messages()
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
        InAppNotifier $notifier,
    ): JsonResponse {
        /** @var OrderMessage $message */
        $message = $order->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $message->load('author.roles');

        $counterpart = $order->isOwnedByBuyer($request->user())
            ? $order->farmerSeller
            : $order->buyer;

        if ($counterpart !== null) {
            $notifier->orderMessage($counterpart, $order, $request->user(), $message->body);
        }

        event(new OrderMessageCreated($message));

        return (new OrderMessageResource($message))
            ->additional(['message' => 'Message sent.'])
            ->response()
            ->setStatusCode(201);
    }
}
