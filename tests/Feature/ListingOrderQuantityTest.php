<?php

namespace Tests\Feature;

use App\Enums\FulfillmentPreference;
use App\Enums\ListingUnit;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ListingOrderQuantityTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_default_listing_sells_whole_units(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10])->refresh();

        $this->assertEquals(1, (float) $listing->min_order_quantity);
        $this->assertEquals(1, (float) $listing->order_step);

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 0.5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity')
            ->assertJsonPath('errors.quantity.0', 'Order at least 1 kg, in steps of 1 kg.');

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 1.5,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'Order at least 1 kg, in steps of 1 kg.');

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.listing.min_order_quantity', 1)
            ->assertJsonPath('data.listing.order_step', 1);
    }

    public function test_a_half_kilogram_step_accepts_one_and_a_half_and_refuses_the_gaps(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'quantity_available' => 10,
            'min_order_quantity' => 1,
            'order_step' => 0.5,
        ]);

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 1.5,
            ])
            ->assertCreated();

        $cartId = $buyer->cartItems()->first()->id;

        $this->asUser($buyer)
            ->patchJson("/api/buyer/cart/{$cartId}", ['quantity' => 1.25])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'Order at least 1 kg, in steps of 0.5 kg.');

        $this->asUser($buyer)
            ->patchJson("/api/buyer/cart/{$cartId}", ['quantity' => 0.5])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'Order at least 1 kg, in steps of 0.5 kg.');
    }

    public function test_a_tray_listing_refuses_a_half_step(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'unit_of_measure' => ListingUnit::Tray,
            'floor_price' => 0,
            'max_discount' => 0,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Egg tray',
                'crop_type_id' => $cropType->id,
                'unit' => 'tray',
                'price_per_unit' => 30,
                'quantity_available' => 10,
                'order_step' => 0.5,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.order_step.0', 'Trays are sold whole. Use a step of 1 or more.');
    }

    public function test_changing_kilograms_to_trays_rechecks_the_step(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'floor_price' => 0,
            'max_discount' => 0,
        ]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'unit' => ListingUnit::Kilogram,
            'min_order_quantity' => 1,
            'order_step' => 0.5,
        ]);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/listings/{$listing->id}", ['unit' => 'tray'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.order_step.0', 'Trays are sold whole. Use a step of 1 or more.');
    }

    public function test_checkout_refuses_a_line_the_seller_later_made_invalid(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Sitaw',
            'quantity_available' => 10,
        ]);

        $this->addToCart($buyer, $listing, 2);

        $listing->update(['min_order_quantity' => 3]);

        $this->asUser($buyer)
            ->postJson('/api/buyer/checkout', [
                'fulfillment_preference' => FulfillmentPreference::BuyerPickup->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.cart.0', 'Sitaw: Order at least 3 kg, in steps of 1 kg.');
    }

    public function test_a_reservation_uses_the_same_quantity_rule(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'quantity_available' => 10,
            'available_from' => now()->addDays(3),
            'available_until' => now()->addDays(10),
        ]);

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $listing->id,
                'quantity' => 1.5,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'Order at least 1 kg, in steps of 1 kg.');

        $listing->update(['order_step' => 0.5]);

        $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'listing_id' => $listing->id,
                'quantity' => 1.5,
                'fulfillment_preference' => 'buyer_pickup',
            ])
            ->assertCreated();
    }

    public function test_a_walk_in_of_a_fraction_still_works(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, [
            'price_per_unit' => 40,
            'quantity_available' => 10,
        ]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 0.3,
                'amount_received' => 20,
            ])
            ->assertCreated();
    }

    public function test_stock_below_the_minimum_is_refused(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 0.4]);

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.quantity.0',
                'Only 0.4 kg left, below the minimum order of 1 kg.',
            );
    }
}
