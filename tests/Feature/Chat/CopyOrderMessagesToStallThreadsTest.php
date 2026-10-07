<?php

namespace Tests\Feature\Chat;

use App\Actions\Chat\CopyOrderMessagesToStallThreads;
use App\Models\OrderMessage;
use App\Models\StallConversation;
use App\Models\StallMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CopyOrderMessagesToStallThreadsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_copy_is_idempotent_and_skips_walk_ins(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, [
            'quantity_available' => 10,
            'price_per_unit' => 30,
        ]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $buyerAt = Carbon::parse('2026-09-01 08:15:00');
        $sellerAt = Carbon::parse('2026-09-01 08:20:00');

        $buyerMessage = $this->orderMessage($order->id, $buyer->id, 'Kita tayo sa hall.', $buyerAt);
        $sellerMessage = $this->orderMessage($order->id, $farmer->id, 'Sige, naka-pack na.', $sellerAt);

        $walkInId = $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'amount_received' => 30,
                'buyer_name' => 'Walk-in',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->orderMessage($walkInId, $farmer->id, 'Walk-in note stays put.', $buyerAt);

        $action = app(CopyOrderMessagesToStallThreads::class);

        $this->assertSame(2, $action->handle());
        $this->assertSame(0, $action->handle());
        $this->assertSame(2, StallMessage::query()->count());
        $this->assertSame(3, OrderMessage::query()->count());

        $copiedBuyer = StallMessage::query()->where('source_order_message_id', $buyerMessage->id)->first();
        $copiedSeller = StallMessage::query()->where('source_order_message_id', $sellerMessage->id)->first();

        $this->assertNotNull($copiedBuyer);
        $this->assertNotNull($copiedSeller);
        $this->assertSame($buyer->id, $copiedBuyer->user_id);
        $this->assertSame($farmer->id, $copiedSeller->user_id);
        $this->assertSame($order->id, $copiedBuyer->order_id);
        $this->assertTrue($copiedBuyer->created_at?->equalTo($buyerAt));
        $this->assertTrue($copiedBuyer->updated_at?->equalTo($buyerAt));
        $this->assertTrue($copiedSeller->created_at?->equalTo($sellerAt));
        $this->assertTrue($copiedSeller->updated_at?->equalTo($sellerAt));
        $this->assertDatabaseMissing('stall_messages', ['body' => 'Walk-in note stays put.']);

        $conversation = StallConversation::query()->first();
        $this->assertNotNull($conversation);
        $this->assertSame($buyer->id, $conversation->buyer_id);
        $this->assertSame($farmer->id, $conversation->farmer_seller_id);
        $this->assertTrue($conversation->updated_at?->equalTo($sellerAt));
    }

    private function orderMessage(int $orderId, int $userId, string $body, Carbon $at): OrderMessage
    {
        $message = OrderMessage::query()->create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'body' => $body,
        ]);
        $message->timestamps = false;
        $message->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $message->refresh();
    }
}
