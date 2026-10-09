<?php

namespace App\Actions\Payments;

use App\Actions\Chat\SendStallMessage;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\StallConversation;
use App\Models\User;
use Throwable;

class PostPaymentChatNote
{
    /**
     * A short note tagged to the order. A chat failure does not undo the payment.
     */
    public function handle(User $sender, Order $order, string $body): void
    {
        if ($order->buyer_id === null) {
            return;
        }

        try {
            $conversation = StallConversation::query()->firstOrCreate([
                'buyer_id' => $order->buyer_id,
                'farmer_seller_id' => $order->farmer_seller_id,
            ]);

            app(SendStallMessage::class)->handle($sender, $conversation, [
                'body' => $body,
                'order_id' => $order->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The same stall thread, without an order tag. Upcoming listings are not
     * on sale yet, so the note is not attached to a listing card.
     */
    public function forReservation(User $sender, Reservation $reservation, string $body): void
    {
        try {
            $conversation = StallConversation::query()->firstOrCreate([
                'buyer_id' => $reservation->buyer_id,
                'farmer_seller_id' => $reservation->farmer_seller_id,
            ]);

            app(SendStallMessage::class)->handle($sender, $conversation, [
                'body' => $body,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
