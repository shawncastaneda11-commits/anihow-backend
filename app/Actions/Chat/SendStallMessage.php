<?php

namespace App\Actions\Chat;

use App\Events\OrderMessageCreated;
use App\Events\StallMessageCreated;
use App\Models\Listing;
use App\Models\Order;
use App\Models\StallConversation;
use App\Models\StallMessage;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Validation\ValidationException;

class SendStallMessage
{
    public function __construct(
        private InAppNotifier $notifier,
        private PresentStallMessages $presentStallMessages,
    ) {}

    /**
     * @param  array{body: string, order_id?: int|null, listing_id?: int|null}  $input
     */
    public function handle(User $sender, StallConversation $conversation, array $input): StallMessage
    {
        $order = $this->taggedOrder($sender, $conversation, $input['order_id'] ?? null);
        $snapshot = $this->listingSnapshot($conversation, $input['listing_id'] ?? null);

        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => $input['body'],
            'order_id' => $order?->id,
            ...$snapshot,
        ]);

        $message->load('author.roles');
        $this->presentStallMessages->links([$message]);

        if ($order !== null) {
            $counterpart = $order->isOwnedByBuyer($sender)
                ? $order->farmerSeller
                : $order->buyer;

            if ($counterpart !== null) {
                $this->notifier->orderMessage($counterpart, $order, $sender, $message->body);
            }
        }

        $this->broadcast($message, $order !== null);

        return $message;
    }

    private function taggedOrder(User $sender, StallConversation $conversation, mixed $orderId): ?Order
    {
        if ($orderId === null || $orderId === '') {
            return null;
        }

        $order = Order::query()->find((int) $orderId);
        $pairMatches = $order !== null
            && $order->buyer_id !== null
            && (int) $order->buyer_id === (int) $conversation->buyer_id
            && (int) $order->farmer_seller_id === (int) $conversation->farmer_seller_id;

        if (! $pairMatches || ! $sender->can('sendMessage', $order)) {
            abort(403);
        }

        return $order;
    }

    /**
     * @return array{
     *     listing_id: int|null,
     *     listing_title: string|null,
     *     listing_price_per_unit: mixed,
     *     listing_unit: string|null,
     *     listing_thumbnail_path: string|null
     * }
     */
    private function listingSnapshot(StallConversation $conversation, mixed $listingId): array
    {
        $empty = [
            'listing_id' => null,
            'listing_title' => null,
            'listing_price_per_unit' => null,
            'listing_unit' => null,
            'listing_thumbnail_path' => null,
        ];

        if ($listingId === null || $listingId === '') {
            return $empty;
        }

        $listing = Listing::query()
            ->listedForBuyers()
            ->whereKey((int) $listingId)
            ->where('farmer_seller_id', $conversation->farmer_seller_id)
            ->first();

        if ($listing === null) {
            throw ValidationException::withMessages([
                'listing_id' => 'Choose a listing this stall is currently selling.',
            ]);
        }

        return [
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
            'listing_price_per_unit' => $listing->price_per_unit,
            'listing_unit' => $listing->unit->value,
            'listing_thumbnail_path' => $listing->image_path,
        ];
    }

    private function broadcast(StallMessage $message, bool $tagged): void
    {
        try {
            event(new StallMessageCreated($message));

            if ($tagged) {
                event(new OrderMessageCreated($message));
            }
        } catch (BroadcastException $exception) {
            report($exception);
        }
    }
}
