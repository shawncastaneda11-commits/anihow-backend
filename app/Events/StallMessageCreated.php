<?php

namespace App\Events;

use App\Actions\Chat\PresentStallMessages;
use App\Http\Resources\Api\StallMessageResource;
use App\Models\StallMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StallMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public StallMessage $message)
    {
        $this->message->loadMissing('author.roles');
        app(PresentStallMessages::class)->links([$this->message]);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('stall-conversations.'.$this->message->stall_conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stall.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'data' => (new StallMessageResource($this->message))->resolve(),
        ];
    }
}
