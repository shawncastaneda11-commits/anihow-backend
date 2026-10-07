<?php

namespace Tests\Feature\Api;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class BuyerOrderApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_order_resource_sends_live_keys_and_omits_retired_ones(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 2);

        $response = $this->asUser($buyer)
            ->getJson("/api/buyer/orders/{$order->id}")
            ->assertOk();

        $data = $response->json('data');
        $item = $data['items'][0];

        $this->assertArrayHasKey('placed_at', $data);
        $this->assertNotEmpty($data['placed_at']);
        $this->assertArrayHasKey('can_be_reviewed', $data);
        $this->assertFalse($data['can_be_reviewed']);
        $this->assertArrayHasKey('listed_price', $item);
        $this->assertEquals(30, $item['listed_price']);

        $this->assertArrayNotHasKey('created_at', $data);
        $this->assertArrayNotHasKey('can_review', $data);
        $this->assertArrayNotHasKey('notes', $data);
        $this->assertArrayNotHasKey('unit_price', $item);
    }

    public function test_buyer_sees_seller_cancellation_note(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/cancel", [
                'reason' => 'other',
                'note' => 'Stall closed after the rain.',
            ])
            ->assertOk();

        $this->asUser($buyer)
            ->getJson("/api/buyer/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.cancellation_reason', 'other')
            ->assertJsonPath('data.cancellation_note', 'Stall closed after the rain.');
    }
}
