<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class StallChatApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_buyer_can_message_a_stall_without_an_order(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Aling Nena Produce']);
        $buyer = $this->buyer();

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->assertJsonPath('data.shop_name', 'Aling Nena Produce')
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Is the tomato still there?'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Is the tomato still there?');

        $this->asUser($farmer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.buyer_name', $buyer->name)
            ->assertJsonPath('data.0.latest_message.body', 'Is the tomato still there?');

        $this->asUser($farmer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Yes, come by this afternoon.'])
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_opening_the_same_stall_returns_the_existing_thread(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();

        $first = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertOk()
            ->assertJsonPath('data.id', $first);
    }

    public function test_another_buyer_cannot_read_the_thread(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $other = $this->buyer(['email' => 'other.buyer@example.com']);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($other)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertForbidden();

        $this->asUser($other)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_a_seller_cannot_start_a_stall_chat(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(['email' => 'other.seller@example.com']);

        $this->asUser($farmer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $other->id])
            ->assertForbidden();
    }

    public function test_an_empty_thread_stays_out_of_the_inbox(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
