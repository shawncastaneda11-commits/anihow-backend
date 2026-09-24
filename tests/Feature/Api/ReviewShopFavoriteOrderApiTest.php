<?php

namespace Tests\Feature\Api;

use App\Actions\Privacy\AnonymizeUserAction;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ReviewShopFavoriteOrderApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_shop_reviews_return_buyer_name_and_order_id_is_required(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Aling Nena Produce']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 20]);
        $buyer = $this->buyer(['name' => 'Maria Buyer']);

        $order = $this->placeOrder($buyer, $listing, 1);
        $this->completeOrder($farmer, $order, 30);

        $this->asUser($buyer)->postJson('/api/buyer/reviews', [
            'reservation_id' => $order->id,
            'rating' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');

        $this->asUser($buyer)->postJson('/api/buyer/reviews', [
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Sariwa ang kamatis.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.order_id', $order->id);

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.buyer_name', 'Maria Buyer')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Sariwa ang kamatis.');
    }

    public function test_buyer_can_view_shop_profile_and_farmer_can_update_own_shop(): void
    {
        $farmer = $this->farmer([
            'name' => 'Juan Dela Cruz',
            'shop_name' => 'Juan Farm Stall',
            'location' => 'San Francisco, General Trias, Cavite',
            'bio' => 'Morning harvest.',
            'contact' => '09171230001',
        ]);
        $this->listingFor($farmer, ['title' => 'Fresh kamatis', 'is_active' => true]);
        $this->listingFor($farmer, ['title' => 'Hidden', 'is_active' => false]);
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/shops')
            ->assertOk()
            ->assertJsonPath('data.0.shop_name', 'Juan Farm Stall');

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonPath('data.shop_name', 'Juan Farm Stall')
            ->assertJsonPath('data.location', 'San Francisco, General Trias, Cavite')
            ->assertJsonCount(1, 'data.listings')
            ->assertJsonPath('data.listings.0.title', 'Fresh kamatis');

        $this->asUser($farmer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertForbidden();

        $suspended = $this->farmer(['email' => 'suspended@example.com', 'status' => UserStatus::Suspended]);
        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$suspended->id}")
            ->assertNotFound();

        $this->asUser($farmer)
            ->patchJson('/api/farmer/shop', [
                'shop_name' => 'San Francisco Stall',
                'bio' => 'Updated bio.',
            ])
            ->assertOk()
            ->assertJsonPath('data.shop_name', 'San Francisco Stall')
            ->assertJsonPath('data.bio', 'Updated bio.');
    }

    public function test_buyer_can_add_list_and_remove_favorites(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/buyer/favorites', [
            'listing_id' => $listing->id,
        ])->assertCreated();

        $this->asUser($buyer)->postJson('/api/buyer/favorites', [
            'listing_id' => $listing->id,
        ])->assertUnprocessable();

        $this->asUser($buyer)
            ->getJson('/api/buyer/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($buyer)
            ->deleteJson("/api/buyer/favorites/{$listing->id}")
            ->assertOk();

        $this->asUser($buyer)
            ->getJson('/api/buyer/favorites')
            ->assertJsonCount(0, 'data');
    }

    public function test_buyer_can_add_list_and_remove_shop_favorites(): void
    {
        $farmer = $this->farmer([
            'shop_name' => 'Aling Nena Produce',
            'email' => 'nena-shop@example.com',
            'phone' => '09171112222',
        ]);
        $buyer = $this->buyer();
        $otherBuyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false);

        $this->asUser($buyer)
            ->postJson('/api/buyer/shop-favorites', [
                'farmer_seller_id' => $farmer->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.shop.shop_name', 'Aling Nena Produce');

        $this->asUser($buyer)
            ->postJson('/api/buyer/shop-favorites', [
                'farmer_seller_id' => $farmer->id,
            ])
            ->assertUnprocessable();

        $this->asUser($buyer)
            ->postJson('/api/buyer/shop-favorites', [
                'farmer_seller_id' => $otherBuyer->id,
            ])
            ->assertUnprocessable();

        $list = $this->asUser($buyer)
            ->getJson('/api/buyer/shop-favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $encoded = json_encode($list->json());
        $this->assertStringNotContainsString('nena-shop@example.com', (string) $encoded);

        $this->asUser($otherBuyer)
            ->getJson('/api/buyer/shop-favorites')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertJsonPath('data.is_favorited', true);

        $this->asUser($buyer)
            ->deleteJson("/api/buyer/shop-favorites/{$farmer->id}")
            ->assertOk();

        $this->asUser($buyer)
            ->getJson('/api/buyer/shop-favorites')
            ->assertJsonCount(0, 'data');
    }

    public function test_farmer_and_content_editor_cannot_manage_shop_favorites(): void
    {
        $farm = $this->farm();
        $seller = $this->farmer([], $farm);
        $target = $this->farmer();
        $editor = User::factory()->create(['farm_id' => $farm->id]);
        $editor->syncRoles(Role::ContentEditor);

        $this->asUser($seller)
            ->postJson('/api/buyer/shop-favorites', ['farmer_seller_id' => $target->id])
            ->assertForbidden();

        $this->asUser($editor)
            ->getJson('/api/buyer/shop-favorites')
            ->assertForbidden();
    }

    public function test_shop_favorites_omit_anonymised_sellers(): void
    {
        $seller = $this->farmer(['shop_name' => 'Aling Nena Produce']);
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/buyer/shop-favorites', [
                'farmer_seller_id' => $seller->id,
            ])
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson('/api/buyer/shop-favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        app(AnonymizeUserAction::class)->handle($seller);

        $this->asUser($buyer)
            ->getJson('/api/buyer/shop-favorites')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
