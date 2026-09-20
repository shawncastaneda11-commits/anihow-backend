<?php

namespace Tests\Feature\Api;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerOrderApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_farmer_walks_an_order_from_placed_to_completed(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 2);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/ready")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Ready->value);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/complete", [
                'amount_received' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Completed->value)
            ->assertJsonPath('data.amount_received', 60);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_completing_without_amount_received_is_rejected(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/ready")->assertOk();

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/complete", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_received');

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
    }

    public function test_cancel_with_no_show_lands_as_cancelled_and_releases_held_stock(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 2);

        $listing->refresh();
        $this->assertSame(2.0, (float) $listing->quantity_held);
        $this->assertSame(10.0, (float) $listing->quantity_available);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/cancel", [
                'reason' => CancellationReason::NoShow->value,
                'note' => 'Buyer did not arrive.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.cancellation_reason', CancellationReason::NoShow->value);

        $this->assertContains($order->fresh()->status->value, array_column(OrderStatus::cases(), 'value'));
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertCount(5, OrderStatus::cases());

        $listing->refresh();
        $this->assertSame(0.0, (float) $listing->quantity_held);
        $this->assertSame(10.0, (float) $listing->quantity_available);
    }

    public function test_seller_cannot_view_another_sellers_order(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $intruder = $this->farmer(['email' => 'intruder@example.com']);
        $listing = $this->listingFor($owner, ['quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($intruder)
            ->getJson("/api/farmer/orders/{$order->id}")
            ->assertForbidden();
    }
}
