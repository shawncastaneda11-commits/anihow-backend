<?php

namespace Tests\Feature\Api;

use App\Enums\FulfillmentPreference;
use App\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_two_seller_cart_produces_two_orders(): void
    {
        $farm = $this->farm();
        $cropType = $this->cropType(['floor_price' => 25, 'max_discount' => 20]);
        $sellerA = $this->farmer(['email' => 'seller.a@example.com'], $farm);
        $sellerB = $this->farmer(['email' => 'seller.b@example.com'], $farm);
        $listingA = $this->listingFor($sellerA, [
            'crop_type_id' => $cropType->id,
            'title' => 'Fresh kamatis, hand picked',
            'price_per_unit' => 30,
            'quantity_available' => 100,
        ]);
        $listingB = $this->listingFor($sellerB, [
            'crop_type_id' => $cropType->id,
            'title' => 'Kamatis, bagong ani',
            'price_per_unit' => 28,
            'quantity_available' => 50,
        ]);
        $buyer = $this->buyer();

        $this->addToCart($buyer, $listingA, 6);
        $this->addToCart($buyer, $listingB, 2);

        $response = $this->checkout($buyer)->assertCreated();
        $response->assertJsonCount(2, 'data');

        $sellerIds = collect($response->json('data'))->pluck('seller.id')->sort()->values();
        $this->assertEqualsCanonicalizing([$sellerA->id, $sellerB->id], $sellerIds->all());
        $this->assertSame(0, $buyer->cartItems()->count());
    }

    public function test_listed_price_tawad_and_total_are_three_separate_values(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType(['floor_price' => 25, 'max_discount' => 20]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 100,
        ]);
        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/tawad", [
            'type' => 'min_quantity',
            'discount_amount' => 20,
            'min_quantity' => 5,
        ])->assertCreated();

        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 6);

        $order = $this->checkout($buyer)
            ->assertCreated()
            ->json('data.0');

        $this->assertEquals(30, $order['items'][0]['listed_price']);
        $this->assertEquals(20, $order['tawad_total']);
        $this->assertEquals(160, $order['total']);
        $this->assertEquals(180, $order['subtotal']);
        $this->assertNotEquals($order['items'][0]['listed_price'], $order['total']);
        $this->assertEquals(30, (float) $listing->fresh()->price_per_unit);
    }

    public function test_checkout_ignores_payment_method_courier_and_fee_and_persists_nothing(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 1);

        $response = $this->asUser($buyer)->postJson('/api/buyer/checkout', [
            'fulfillment_preference' => FulfillmentPreference::BuyerPickup->value,
            'fulfillment_note' => 'Saturday 7am at the hall.',
            'payment_method' => 'gcash',
            'courier' => 'Lalamove',
            'fee' => 50,
        ])->assertCreated();

        $order = Order::query()->findOrFail($response->json('data.0.id'));

        $this->assertSame('cash_on_handover', $order->payment_method);
        $this->assertSame('Saturday 7am at the hall.', $order->fulfillment_note);
        $this->assertArrayNotHasKey('courier', $order->getAttributes());
        $this->assertArrayNotHasKey('fee', $order->getAttributes());
        $this->assertFalse(Schema::hasColumn('orders', 'courier'));
        $this->assertFalse(Schema::hasColumn('orders', 'fee'));
        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
            'payment_method' => 'gcash',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'cash_on_handover',
            'fulfillment_note' => 'Saturday 7am at the hall.',
        ]);
    }
}
