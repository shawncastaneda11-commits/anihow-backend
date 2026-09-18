<?php

namespace Tests\Feature\Api;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewShopFavoriteOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_can_review_only_a_completed_reservation_once(): void
    {
        [$farmer, $listing, $buyer] = $this->completedReservation();

        $pendingId = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->asUser($buyer)->postJson('/api/buyer/reviews', [
            'reservation_id' => $pendingId,
            'rating' => 5,
        ])->assertUnprocessable();

        $completed = $buyer->buyerReservations()
            ->where('status', ReservationStatus::Completed)
            ->first();

        $this->asUser($buyer)->postJson('/api/buyer/reviews', [
            'reservation_id' => $completed->id,
            'rating' => 5,
            'comment' => 'Fresh kamote.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5);

        $this->asUser($buyer)->postJson('/api/buyer/reviews', [
            'reservation_id' => $completed->id,
            'rating' => 4,
        ])->assertUnprocessable();

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Fresh kamote.')
            ->assertJsonPath('data.0.buyer.name', $buyer->name)
            ->assertJsonPath('average_rating', '5.0')
            ->assertJsonPath('reviews_count', 1);

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonPath('data.average_rating', '5.0')
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.listings.0.average_rating', '5.0')
            ->assertJsonPath('data.listings.0.seller.average_rating', '5.0');
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
        Listing::factory()->forFarmer($farmer)->create(['name' => 'Tomato', 'is_active' => true]);
        Listing::factory()->forFarmer($farmer)->inactive()->create(['name' => 'Hidden']);
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
            ->assertJsonPath('data.contact', '09171230001')
            ->assertJsonCount(1, 'data.listings')
            ->assertJsonPath('data.listings.0.name', 'Tomato')
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.reviews_count', 0);

        $this->asUser($farmer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertForbidden();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/buyer/shops/{$farmer->id}")->assertUnauthorized();

        $inactive = $this->farmer(['is_active' => false]);
        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$inactive->id}")
            ->assertNotFound();
        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$inactive->id}/reviews")
            ->assertNotFound();

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$buyer->id}")
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
        $listing = Listing::factory()->forFarmer($farmer)->create();
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

    public function test_order_history_lists_past_reservations_and_receipt_breakdown(): void
    {
        [, $listing, $buyer] = $this->completedReservation();

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ]);

        $history = $this->asUser($buyer)->getJson('/api/buyer/orders')->assertOk();
        $history->assertJsonCount(1, 'data');
        $history->assertJsonPath('data.0.status', ReservationStatus::Completed->value);

        $id = $history->json('data.0.id');

        $this->asUser($buyer)
            ->getJson("/api/buyer/orders/{$id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.reservation_id', $id)
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonStructure(['data' => ['items', 'total', 'seller']]);
    }

    /**
     * @return array{0: User, 1: Listing, 2: User}
     */
    private function completedReservation(): array
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create(['quantity_available' => 20]);
        $buyer = $this->buyer();

        $id = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 2]],
            ])
            ->json('data.id');

        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$id}/ready")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$id}/complete")->assertOk();

        return [$farmer, $listing->fresh(), $buyer];
    }

    private function farmer(array $attributes = []): User
    {
        $farmer = User::factory()->create($attributes);
        $farmer->assignRole(Role::FarmerSeller);

        return $farmer;
    }

    private function buyer(array $attributes = []): User
    {
        $buyer = User::factory()->create($attributes);
        $buyer->assignRole(Role::Buyer);

        return $buyer;
    }

    private function asUser(User $user): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('mobile')->plainTextToken);
    }
}
