<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class OrderLatestMessageTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_order_lists_include_the_newest_tagged_message_and_its_author(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Is it ready?'])
            ->assertCreated();

        $this->asUser($farmer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Yes, come by today.'])
            ->assertCreated();

        $this->asUser($farmer)
            ->getJson('/api/farmer/orders')
            ->assertOk()
            ->assertJsonPath('data.0.latest_message.body', 'Yes, come by today.')
            ->assertJsonPath('data.0.latest_message.user_id', $farmer->id)
            ->assertJsonPath('data.0.latest_message.created_at', fn ($value) => is_string($value) && $value !== '');

        $this->asUser($buyer)
            ->getJson('/api/buyer/orders')
            ->assertOk()
            ->assertJsonPath('data.0.latest_message.body', 'Yes, come by today.')
            ->assertJsonPath('data.0.latest_message.user_id', $farmer->id);
    }

    public function test_an_order_with_no_messages_has_a_null_latest_message(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $this->placeOrder($this->buyer(), $listing, 1);

        $this->asUser($farmer)
            ->getJson('/api/farmer/orders')
            ->assertOk()
            ->assertJsonPath('data.0.latest_message', null);
    }

    public function test_a_stall_conversation_latest_message_includes_the_author(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Still open?'])
            ->assertCreated();

        $this->asUser($farmer)
            ->getJson('/api/stall-chats')
            ->assertOk()
            ->assertJsonPath('data.0.latest_message.body', 'Still open?')
            ->assertJsonPath('data.0.latest_message.user_id', $buyer->id);
    }

    public function test_the_order_list_loads_the_latest_message_in_one_query(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $first = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($buyer)
            ->postJson("/api/orders/{$first->id}/messages", ['body' => 'First'])
            ->assertCreated();

        $one = $this->stallMessageQueries(fn () => $this->asUser($farmer)->getJson('/api/farmer/orders')->assertOk());

        $this->placeOrder($buyer, $listing, 1);
        $third = $this->placeOrder($buyer, $listing, 1);
        $this->asUser($farmer)
            ->postJson("/api/orders/{$third->id}/messages", ['body' => 'Third'])
            ->assertCreated();

        $three = $this->stallMessageQueries(fn () => $this->asUser($farmer)->getJson('/api/farmer/orders')->assertOk()->assertJsonCount(3, 'data'));

        $this->assertSame(1, $one);
        $this->assertSame($one, $three);
    }

    private function stallMessageQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'stall_messages'))
            ->count();
    }
}
