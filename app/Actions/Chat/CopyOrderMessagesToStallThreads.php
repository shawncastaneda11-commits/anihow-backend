<?php

namespace App\Actions\Chat;

use App\Models\OrderMessage;
use App\Models\StallConversation;
use App\Models\StallMessage;

class CopyOrderMessagesToStallThreads
{
    /**
     * Copy each app-order chat row into that buyer and seller's stall thread.
     * Walk-ins are skipped. A row already copied is left alone, so a second
     * run during deploy does not duplicate history.
     */
    public function handle(): int
    {
        $copied = 0;

        OrderMessage::query()
            ->with('order')
            ->orderBy('id')
            ->chunkById(200, function ($messages) use (&$copied): void {
                foreach ($messages as $message) {
                    if ($this->copyOne($message)) {
                        $copied++;
                    }
                }
            });

        return $copied;
    }

    private function copyOne(OrderMessage $message): bool
    {
        if (StallMessage::query()->where('source_order_message_id', $message->id)->exists()) {
            return false;
        }

        $order = $message->order;

        if ($order === null || $order->isWalkIn() || $order->buyer_id === null) {
            return false;
        }

        $conversation = StallConversation::query()->firstOrCreate([
            'buyer_id' => $order->buyer_id,
            'farmer_seller_id' => $order->farmer_seller_id,
        ]);

        $stallMessage = new StallMessage([
            'stall_conversation_id' => $conversation->id,
            'user_id' => $message->user_id,
            'body' => $message->body,
            'order_id' => $order->id,
            'source_order_message_id' => $message->id,
        ]);
        $stallMessage->created_at = $message->created_at;
        $stallMessage->updated_at = $message->updated_at;
        $stallMessage->timestamps = false;
        $stallMessage->saveWithoutTouching();

        $latest = StallMessage::query()
            ->where('stall_conversation_id', $conversation->id)
            ->max('created_at');

        if ($latest !== null) {
            $conversation->timestamps = false;
            $conversation->forceFill(['updated_at' => $latest])->save();
        }

        return true;
    }
}
