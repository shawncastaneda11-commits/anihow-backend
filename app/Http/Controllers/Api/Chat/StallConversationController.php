<?php

namespace App\Http\Controllers\Api\Chat;

use App\Actions\Chat\PresentStallMessages;
use App\Actions\Chat\SendStallMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chat\StoreStallConversationRequest;
use App\Http\Requests\Api\Chat\StoreStallMessageRequest;
use App\Http\Resources\Api\StallConversationResource;
use App\Http\Resources\Api\StallMessageResource;
use App\Models\StallConversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StallConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StallConversation::class);

        $user = $request->user();
        $conversations = StallConversation::query()
            ->whereHas('messages')
            ->with(['buyer', 'farmerSeller', 'latestMessage'])
            ->when(
                $user->isBuyer(),
                fn ($query) => $query->where('buyer_id', $user->id),
                fn ($query) => $query->where('farmer_seller_id', $user->id),
            )
            ->latest('updated_at')
            ->get();

        return StallConversationResource::collection($conversations);
    }

    public function store(StoreStallConversationRequest $request): JsonResponse
    {
        $seller = User::query()->findOrFail($request->integer('farmer_seller_id'));
        abort_unless($seller->isFarmerSeller() && $seller->isActive(), 404);

        $conversation = StallConversation::query()->firstOrCreate([
            'buyer_id' => $request->user()->id,
            'farmer_seller_id' => $seller->id,
        ]);

        $conversation->load(['buyer', 'farmerSeller', 'latestMessage']);

        return (new StallConversationResource($conversation))
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function messages(
        Request $request,
        StallConversation $stallConversation,
        PresentStallMessages $presentStallMessages,
    ): AnonymousResourceCollection {
        $this->authorize('view', $stallConversation);

        $messages = $stallConversation->messages()
            ->with('author.roles')
            ->when(
                $request->filled('after_id'),
                fn ($query) => $query->where('id', '>', $request->integer('after_id')),
            )
            ->orderBy('id')
            ->get();

        $presentStallMessages->links($messages);

        return StallMessageResource::collection($messages);
    }

    public function storeMessage(
        StoreStallMessageRequest $request,
        StallConversation $stallConversation,
        SendStallMessage $sendStallMessage,
    ): JsonResponse {
        $message = $sendStallMessage->handle($request->user(), $stallConversation, [
            'body' => $request->validated('body'),
            'order_id' => $request->validated('order_id'),
            'listing_id' => $request->validated('listing_id'),
        ]);

        return (new StallMessageResource($message))
            ->additional(['message' => 'Message sent.'])
            ->response()
            ->setStatusCode(201);
    }
}
