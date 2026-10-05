<?php

namespace Tests\Feature\Api;

use App\Models\StallConversation;
use App\Models\StallMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class RemoveStallChatTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_removing_a_chat_hides_it_for_that_person_only_until_a_new_message(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Aling Nena Produce']);
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $order = $this->placeOrder($buyer, $listing, 1);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertSuccessful()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'See you at the stall.'])
            ->assertCreated();

        $this->asUser($farmer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Bring a bag.'])
            ->assertCreated();

        $messageCount = StallMessage::query()->count();

        $this->asUser($buyer)
            ->deleteJson("/api/stall-chats/{$conversationId}")
            ->assertNoContent();

        $this->asUser($buyer)
            ->deleteJson("/api/stall-chats/{$conversationId}")
            ->assertNoContent();

        $this->assertSame($messageCount, StallMessage::query()->count());

        $conversation = StallConversation::query()->findOrFail($conversationId);
        $this->assertNotNull($conversation->buyer_cleared_through_message_id);
        $this->assertNull($conversation->farmer_seller_cleared_through_message_id);

        $this->asUser($buyer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($buyer)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($farmer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latest_message.body', 'Bring a bag.');

        $this->asUser($farmer)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->asUser($buyer)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonFragment(['body' => 'See you at the stall.']);

        $this->asUser($farmer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'I am here.'])
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latest_message.body', 'I am here.');

        $this->asUser($buyer)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'I am here.');

        $this->asUser($farmer)
            ->deleteJson("/api/stall-chats/{$conversationId}")
            ->assertNoContent();

        $conversation->refresh();
        $this->assertNotNull($conversation->farmer_seller_cleared_through_message_id);
        $this->assertNotNull($conversation->buyer_cleared_through_message_id);

        $this->asUser($farmer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($buyer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($buyer)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonFragment(['body' => 'See you at the stall.']);

        $this->assertSame($messageCount + 1, StallMessage::query()->count());
    }

    public function test_an_outsider_cannot_remove_a_chat_and_a_guest_is_unauthorized(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $other = $this->buyer(['email' => 'other.buyer@example.com']);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Hello'])
            ->assertCreated();

        $this->asUser($other)
            ->deleteJson("/api/stall-chats/{$conversationId}")
            ->assertForbidden();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->deleteJson("/api/stall-chats/{$conversationId}")
            ->assertUnauthorized();

        $this->assertNull(StallConversation::query()->find($conversationId)?->buyer_cleared_through_message_id);
    }
}
