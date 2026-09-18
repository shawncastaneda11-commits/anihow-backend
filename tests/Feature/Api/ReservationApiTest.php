<?php

namespace Tests\Feature\Api;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_can_create_view_and_cancel_a_pending_reservation(): void
    {
        $farmer = $this->farmer([
            'shop_name' => 'Juan Farm Stall',
            'location' => 'San Francisco, General Trias, Cavite',
            'contact' => '09171230001',
        ]);
        $listing = Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Tomato',
            'quantity_available' => 10,
            'price_per_unit' => 65,
            'category_id' => Category::factory(),
        ]);
        $buyer = $this->buyer();

        $create = $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'notes' => 'Pickup Saturday',
            'items' => [
                ['listing_id' => $listing->id, 'quantity' => 2],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', ReservationStatus::Pending->value)
            ->assertJsonPath('data.total', '130.00');

        $this->assertEquals('8.00', $listing->fresh()->quantity_available);

        $id = $create->json('data.id');

        $this->asUser($buyer)
            ->getJson("/api/buyer/reservations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.seller.shop_name', 'Juan Farm Stall')
            ->assertJsonPath('data.seller.location', 'San Francisco, General Trias, Cavite')
            ->assertJsonPath('data.seller.contact', '09171230001')
            ->assertJsonPath('data.items.0.listing_name', 'Tomato')
            ->assertJsonPath('data.items.0.quantity', '2.00')
            ->assertJsonPath('data.items.0.unit_price', '65.00')
            ->assertJsonPath('data.items.0.line_subtotal', '130.00')
            ->assertJsonPath('data.can_review', false);

        $this->asUser($buyer)
            ->getJson('/api/buyer/reservations')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($buyer)
            ->patchJson("/api/buyer/reservations/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);

        $this->assertEquals('10.00', $listing->fresh()->quantity_available);
    }

    public function test_reservation_rejects_overselling_and_mixed_sellers(): void
    {
        $juan = $this->farmer(['email' => 'juan@example.com']);
        $maria = $this->farmer(['email' => 'maria@example.com']);
        $juanListing = Listing::factory()->forFarmer($juan)->create(['quantity_available' => 1]);
        $mariaListing = Listing::factory()->forFarmer($maria)->create(['quantity_available' => 5]);
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'items' => [
                ['listing_id' => $juanListing->id, 'quantity' => 3],
            ],
        ])->assertUnprocessable();

        $this->assertEquals('1.00', $juanListing->fresh()->quantity_available);

        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'items' => [
                ['listing_id' => $juanListing->id, 'quantity' => 1],
                ['listing_id' => $mariaListing->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();
    }

    public function test_farmer_can_mark_ready_and_complete_incoming_reservations(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create(['quantity_available' => 10]);
        $buyer = $this->buyer();

        $reservationId = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->asUser($farmer)
            ->patchJson("/api/farmer/reservations/{$reservationId}/complete")
            ->assertUnprocessable();

        $this->asUser($farmer)
            ->patchJson("/api/farmer/reservations/{$reservationId}/ready")
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::ReadyForPickup->value);

        $this->asUser($buyer)
            ->patchJson("/api/buyer/reservations/{$reservationId}/cancel")
            ->assertUnprocessable();

        $this->asUser($farmer)
            ->patchJson("/api/farmer/reservations/{$reservationId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Completed->value);

        $this->assertEquals('9.00', $listing->fresh()->quantity_available);

        $this->asUser($buyer)
            ->getJson("/api/buyer/reservations/{$reservationId}")
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Completed->value)
            ->assertJsonPath('data.can_review', true)
            ->assertJsonPath('data.review', null);

        $this->asUser($buyer)
            ->postJson('/api/buyer/reviews', [
                'reservation_id' => $reservationId,
                'rating' => 5,
                'comment' => 'Picked up fresh.',
            ])
            ->assertCreated();

        $this->asUser($buyer)
            ->getJson("/api/buyer/reservations/{$reservationId}")
            ->assertOk()
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.review.rating', 5);
    }

    public function test_farmer_cancel_requires_a_reason_and_restores_stock(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create(['quantity_available' => 5]);
        $buyer = $this->buyer();

        $id = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 2]],
            ])
            ->json('data.id');

        $this->asUser($farmer)
            ->patchJson("/api/farmer/reservations/{$id}/cancel")
            ->assertUnprocessable();

        $this->asUser($farmer)
            ->patchJson("/api/farmer/reservations/{$id}/cancel", [
                'reason' => 'Harvest delayed by rain.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value)
            ->assertJsonPath('data.cancelled_by', 'farmer_seller');

        $this->assertEquals('5.00', $listing->fresh()->quantity_available);
    }

    public function test_farmer_cannot_view_another_farmers_reservation(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $other = $this->farmer(['email' => 'other@example.com']);
        $listing = Listing::factory()->forFarmer($owner)->create();
        $buyer = $this->buyer();

        $id = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->asUser($other)
            ->getJson("/api/farmer/reservations/{$id}")
            ->assertForbidden();
    }

    public function test_buyer_cannot_view_another_buyers_reservation(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create();
        $buyer = $this->buyer();
        $other = $this->buyer(['email' => 'other.buyer@example.com']);

        $id = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->asUser($other)
            ->getJson("/api/buyer/reservations/{$id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_buyer_reservation_show_returns_401(): void
    {
        $this->getJson('/api/buyer/reservations/1')->assertUnauthorized();
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
