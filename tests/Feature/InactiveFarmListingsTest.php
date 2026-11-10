<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class InactiveFarmListingsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_an_inactive_farms_listings_leave_the_marketplace_and_return_with_it(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Pechay ng bukid']);
        $buyer = $this->buyer();
        $titles = fn (): array => collect($this->asUser($buyer)->getJson('/api/buyer/marketplace')->assertOk()->json('data'))
            ->pluck('title')->all();

        $this->assertContains('Pechay ng bukid', $titles());

        $farmer->farm->forceFill(['is_active' => false])->save();

        $this->assertNotContains('Pechay ng bukid', $titles());
        $this->asUser($buyer)->postJson('/api/buyer/cart', ['listing_id' => $listing->id, 'quantity' => 1])->assertNotFound();
        $this->assertTrue($listing->fresh()->is_active);

        $farmer->farm->forceFill(['is_active' => true])->save();

        $this->assertContains('Pechay ng bukid', $titles());
    }

    public function test_a_cart_item_from_a_farm_set_inactive_is_dropped_at_checkout(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 1);

        $farmer->farm->forceFill(['is_active' => false])->save();

        $this->checkout($buyer)->assertUnprocessable();
        $this->assertSame(0.0, (float) $listing->fresh()->quantity_held);
    }
}
