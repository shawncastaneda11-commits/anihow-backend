<?php

namespace Tests\Feature\Api;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\OrderMessageCreated;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class OrderChatApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_and_seller_can_list_and_send_on_their_app_order(): void
    {
        Event::fake([OrderMessageCreated::class]);

        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $firstId = $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'What time for pickup?'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'What time for pickup?')
            ->assertJsonPath('data.author.id', $buyer->id)
            ->assertJsonPath('data.author.role', Role::Buyer->value)
            ->json('data.id');

        $this->asUser($farmer)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'What time for pickup?');

        $this->asUser($farmer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Saturday 7am at the stall.'])
            ->assertCreated()
            ->assertJsonPath('data.author.role', Role::FarmerSeller->value);

        $this->asUser($buyer)
            ->getJson("/api/orders/{$order->id}/messages?after_id={$firstId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Saturday 7am at the stall.');

        Event::assertDispatched(OrderMessageCreated::class);
    }

    public function test_other_seller_cannot_open_another_orders_thread(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $intruder = $this->farmer(['email' => 'intruder@example.com']);
        $listing = $this->listingFor($owner, ['quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($intruder)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertForbidden();

        $this->asUser($intruder)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_walk_in_orders_have_no_chat(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, [
            'quantity_available' => 10,
            'price_per_unit' => 30,
        ]);

        $orderId = $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'amount_received' => 30,
                'buyer_name' => 'Walk-in',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($farmer)
            ->getJson("/api/orders/{$orderId}/messages")
            ->assertForbidden();

        $this->asUser($farmer)
            ->postJson("/api/orders/{$orderId}/messages", ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_cancelled_orders_are_readable_but_not_writable(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Before cancel'])
            ->assertCreated();

        $this->asUser($buyer)
            ->patchJson("/api/buyer/orders/{$order->id}/cancel", [
                'note' => 'Changed plans',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->asUser($buyer)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'After cancel'])
            ->assertForbidden();

        $this->asUser($farmer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Seller after cancel'])
            ->assertForbidden();
    }

    public function test_super_admin_can_read_but_cannot_send(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Buyer note'])
            ->assertCreated();

        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        $this->asUser($admin)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($admin)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Admin reply'])
            ->assertForbidden();
    }

    public function test_sending_notifies_the_counterpart_only(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $farmer->inAppNotifications()->delete();
        $buyer->inAppNotifications()->delete();

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Can we meet earlier?'])
            ->assertCreated();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $farmer->id,
            'type' => NotificationType::OrderMessage->value,
            'related_id' => $order->id,
        ]);

        $this->assertDatabaseMissing('in_app_notifications', [
            'user_id' => $buyer->id,
            'type' => NotificationType::OrderMessage->value,
        ]);
    }

    public function test_empty_body_is_rejected(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }
}
