<?php

namespace App\Events;

use App\Http\Resources\Api\OrderMessageResource;
use App\Models\OrderMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public OrderMessage $message)
    {
        $this->message->loadMissing('author.roles');
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.'.$this->message->order_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'data' => (new OrderMessageResource($this->message))->resolve(),
        ];
    }
}
