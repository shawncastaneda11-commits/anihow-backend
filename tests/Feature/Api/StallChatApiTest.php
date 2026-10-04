<?php

namespace Tests\Feature\Api;

use App\Enums\ListingStatus;
use App\Enums\ListingUnit;
use App\Enums\NotificationType;
use App\Models\Listing;
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

    public function test_an_untagged_stall_message_does_not_notify(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $farmer->inAppNotifications()->delete();

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", ['body' => 'Still open?'])
            ->assertCreated();

        $this->assertDatabaseMissing('in_app_notifications', [
            'type' => NotificationType::OrderMessage->value,
        ]);
    }

    public function test_a_buyer_cannot_tag_another_buyers_order(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $other = $this->buyer(['email' => 'other.buyer@example.com']);
        $order = $this->placeOrder($other, $listing, 1);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'That is not my order.',
                'order_id' => $order->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('stall_messages', ['body' => 'That is not my order.']);
    }

    public function test_a_seller_cannot_tag_an_order_from_a_different_buyer(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $other = $this->buyer(['email' => 'other.buyer@example.com']);
        $order = $this->placeOrder($other, $listing, 1);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($farmer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'Wrong buyer.',
                'order_id' => $order->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('stall_messages', ['body' => 'Wrong buyer.']);
    }

    public function test_a_product_card_keeps_its_snapshot_after_the_listing_is_deleted(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Morning tomatoes',
            'price_per_unit' => 40,
            'unit' => ListingUnit::Kilogram,
            'quantity_available' => 10,
        ]);
        $other = $this->farmer(['email' => 'other.seller@example.com']);
        $foreign = $this->listingFor($other, [
            'title' => 'Someone else\'s pechay',
            'quantity_available' => 10,
        ]);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'Not this stall.',
                'listing_id' => $foreign->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');

        $listing->forceFill(['status' => ListingStatus::TakenDown])->save();

        $this->asUser($farmer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'Taken down.',
                'listing_id' => $listing->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');

        $listing->forceFill(['status' => ListingStatus::Published])->save();

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'Is this the one?',
                'listing_id' => $listing->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.listing_id', $listing->id)
            ->assertJsonPath('data.listing_title', 'Morning tomatoes')
            ->assertJsonPath('data.listing_price_per_unit', '40.0000')
            ->assertJsonPath('data.listing_unit', 'kg')
            ->assertJsonPath('data.order_id', null);

        $listing->forceDelete();

        $this->asUser($buyer)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.listing_id', null)
            ->assertJsonPath('data.0.listing_title', 'Morning tomatoes')
            ->assertJsonPath('data.0.listing_price_per_unit', '40.0000')
            ->assertJsonPath('data.0.listing_unit', 'kg');

        $this->assertDatabaseHas('stall_messages', [
            'listing_title' => 'Morning tomatoes',
            'listing_id' => null,
        ]);
    }

    public function test_a_card_stops_linking_when_the_listing_leaves_the_catalogue(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Market kamote',
            'price_per_unit' => 25,
            'unit' => ListingUnit::Kilogram,
            'quantity_available' => 8,
        ]);

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($farmer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'This one is ready.',
                'listing_id' => $listing->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.listing_id', $listing->id);

        $listing->forceFill(['status' => ListingStatus::TakenDown])->save();

        $this->asUser($buyer)
            ->getJson("/api/stall-chats/{$conversationId}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.listing_id', null)
            ->assertJsonPath('data.0.listing_title', 'Market kamote')
            ->assertJsonPath('data.0.listing_price_per_unit', '25.0000')
            ->assertJsonPath('data.0.listing_unit', 'kg');

        $this->assertNotNull(Listing::query()->find($listing->id));
    }

    public function test_tagging_this_threads_order_notifies_like_the_order_endpoint(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);
        $farmer->inAppNotifications()->delete();

        $conversationId = $this->asUser($buyer)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $farmer->id])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($buyer)
            ->postJson("/api/stall-chats/{$conversationId}/messages", [
                'body' => 'On my way.',
                'order_id' => $order->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.order_id', $order->id);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $farmer->id,
            'type' => NotificationType::OrderMessage->value,
            'related_id' => $order->id,
        ]);
        $this->assertDatabaseCount('order_messages', 0);
    }
}
