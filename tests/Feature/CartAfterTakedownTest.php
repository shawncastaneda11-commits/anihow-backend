<?php

namespace Tests\Feature;

use App\Actions\Listings\TakeDownListingAction;
use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CartAfterTakedownTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_taking_down_a_listing_removes_it_from_buyer_carts(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Talong, mahaba']);
        $other = $this->listingFor($farmer, ['title' => 'Sitaw, sariwa']);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 2);
        $this->addToCart($buyer, $other, 1);

        app(TakeDownListingAction::class)->handle(
            $listing,
            $this->admin(),
            'Item not allowed to be sold.',
        );

        $this->assertSame(1, $buyer->cartItems()->count());
        $this->assertFalse($buyer->cartItems()->where('listing_id', $listing->id)->exists());

        $this->asUser($buyer)
            ->getJson('/api/buyer/cart')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.listing.title', 'Sitaw, sariwa');
    }

    public function test_cart_index_drops_a_listing_that_is_no_longer_on_the_market(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Talong, mahaba']);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 3);

        $listing->forceFill([
            'status' => ListingStatus::TakenDown,
            'is_active' => false,
        ])->save();

        $this->asUser($buyer)
            ->getJson('/api/buyer/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(0, $buyer->cartItems()->count());
    }

    public function test_cart_quantity_cannot_be_updated_after_takedown(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 2);
        $cartItemId = $buyer->cartItems()->value('id');

        $listing->forceFill([
            'status' => ListingStatus::TakenDown,
            'is_active' => false,
        ])->save();

        $this->asUser($buyer)
            ->patchJson('/api/buyer/cart/'.$cartItemId, ['quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(0, $buyer->cartItems()->count());
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        return $admin;
    }
}
