<?php

namespace Tests\Feature\Api;

use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Enums\ListingStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\TawadType;
use App\Http\Resources\Api\OrderResource;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\TawadRule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * Change B: walk-in sales.
 *
 * A farmer-seller records an in-person sale to someone without the app. It
 * lands in the same order ledger, directly at Completed, with no buyer.
 *
 * Every test uses a crop type with a system floor of 25 and a system maximum
 * tawad of 20, the same baseline as the rest of the suite.
 */
class WalkInSaleTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    // Recording

    public function test_a_farmer_records_a_walk_in_sale_directly_at_completed(): void
    {
        $farmer = $this->farmer(['email' => 'stall@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $response = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 2,
            'amount_received' => 60,
            'buyer_name' => 'Aling Nena',
        ])->assertCreated();

        $this->assertSame(OrderStatus::Completed->value, $response->json('data.status'));
        $this->assertSame(OrderSource::WalkIn->value, $response->json('data.source'));
        $this->assertTrue($response->json('data.is_walk_in'));
        $this->assertSame('Aling Nena', $response->json('data.walk_in_buyer_name'));
        $this->assertEquals(30, $response->json('data.items.0.listed_price'));
        $this->assertEquals(60, $response->json('data.total'));
        $this->assertEquals(60, $response->json('data.amount_received'));

        $order = Order::query()->findOrFail($response->json('data.id'));

        $this->assertNull($order->buyer_id);
        $this->assertSame($farmer->id, $order->farmer_seller_id);
        $this->assertSame($listing->farm_id, $order->farm_id);
        $this->assertNotNull($order->completed_at);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_a_walk_in_deducts_stock_at_once_and_holds_nothing(): void
    {
        $farmer = $this->farmer(['email' => 'stock@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 2,
            'amount_received' => 60,
        ])->assertCreated();

        $listing->refresh();
        $this->assertSame(8.0, (float) $listing->quantity_available);
        $this->assertSame(0.0, (float) $listing->quantity_held);
    }

    public function test_the_seller_cannot_type_a_price(): void
    {
        $farmer = $this->farmer(['email' => 'price@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $response = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 1,
            'price_per_unit' => 1,
        ])->assertCreated();

        // The listing's price, whatever was sent. Amount received may differ
        // from the total, as on an app order.
        $this->assertEquals(30, $response->json('data.items.0.listed_price'));
        $this->assertEquals(30, $response->json('data.total'));
        $this->assertEquals(1, $response->json('data.amount_received'));
    }

    public function test_amount_received_is_required(): void
    {
        $farmer = $this->farmer(['email' => 'amount@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_received');

        $this->assertSame(0, Order::query()->count());
    }

    // Pricing, on the same rules as an app order

    public function test_a_walk_in_applies_tawad_on_the_same_rules(): void
    {
        $farmer = $this->farmer(['email' => 'tawad@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 20);
        TawadRule::factory()->for($listing)->create([
            'type' => TawadType::MinimumQuantity,
            'discount_amount' => 20,
            'min_quantity' => 5,
            'is_active' => true,
        ]);

        $order = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 6,
            'amount_received' => 160,
        ])->assertCreated()->json('data');

        $this->assertEquals(180, $order['subtotal']);
        $this->assertEquals(20, $order['tawad_total']);
        $this->assertEquals(160, $order['total']);
        $this->assertEquals(30, $order['items'][0]['listed_price']);
    }

    public function test_a_walk_in_skips_a_tawad_the_farm_ceiling_no_longer_allows(): void
    {
        $farm = Farm::factory()->create();
        $farmer = $this->farmer(['email' => 'ceiling@example.com'], $farm);
        $listing = $this->kamatisListing($farmer, price: 40, quantity: 10);
        TawadRule::factory()->for($listing)->create([
            'type' => TawadType::Flat,
            'discount_amount' => 10,
            'min_quantity' => null,
            'is_active' => true,
        ]);

        app(SetFarmPriceOverrideAction::class)->execute($farm, $listing->cropType, null, 5);

        $order = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 40,
        ])->assertCreated()->json('data');

        $this->assertEquals(0, $order['tawad_total']);
        $this->assertEquals(40, $order['total']);
    }

    public function test_a_listing_below_its_farms_floor_cannot_record_a_walk_in(): void
    {
        $farm = Farm::factory()->create();
        $farmer = $this->farmer(['email' => 'floor@example.com'], $farm);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        app(SetFarmPriceOverrideAction::class)->execute($farm, $listing->cropType, 35, null);

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');

        $this->assertSame(10.0, (float) $listing->fresh()->quantity_available);
    }

    // Which listings qualify

    public function test_a_walk_in_cannot_sell_stock_held_for_app_orders(): void
    {
        $farmer = $this->farmer(['email' => 'held@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);
        $this->placeOrder($this->buyer(), $listing, 8);

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 3,
            'amount_received' => 90,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 2,
            'amount_received' => 60,
        ])->assertCreated();

        $listing->refresh();
        $this->assertSame(8.0, (float) $listing->quantity_available);
        $this->assertSame(8.0, (float) $listing->quantity_held);
    }

    public function test_a_paused_listing_can_still_record_a_walk_in(): void
    {
        $farmer = $this->farmer(['email' => 'paused@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);
        $listing->forceFill(['is_active' => false])->save();

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])->assertCreated();
    }

    public function test_a_taken_down_listing_cannot_record_a_walk_in(): void
    {
        $farmer = $this->farmer(['email' => 'takendown@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);
        $listing->forceFill(['status' => ListingStatus::TakenDown, 'taken_down_at' => now()])->save();

        $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');
    }

    // Who may record one

    public function test_a_seller_cannot_record_a_walk_in_on_another_sellers_listing(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $intruder = $this->farmer(['email' => 'intruder@example.com']);
        $listing = $this->kamatisListing($owner, price: 30, quantity: 10);

        $this->walkIn($intruder, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])->assertForbidden();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(10.0, (float) $listing->fresh()->quantity_available);
    }

    public function test_a_buyer_cannot_record_a_walk_in(): void
    {
        $farmer = $this->farmer(['email' => 'seller@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $this->walkIn($this->buyer(), [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])->assertForbidden();

        $this->assertSame(0, Order::query()->count());
    }

    // No buyer

    public function test_a_walk_in_cannot_be_reviewed(): void
    {
        $farmer = $this->farmer(['email' => 'review@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $response = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])->assertCreated();

        $this->assertFalse($response->json('data.can_be_reviewed'));

        $this->asUser($this->buyer())
            ->postJson('/api/buyer/reviews', [
                'order_id' => $response->json('data.id'),
                'rating' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');
    }

    public function test_the_walk_in_name_is_sent_only_to_the_seller_who_recorded_it(): void
    {
        $farmer = $this->farmer(['email' => 'name@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $orderId = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
            'buyer_name' => 'Mang Tonyo',
        ])->assertCreated()->json('data.id');

        $this->asUser($farmer)
            ->getJson("/api/farmer/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.walk_in_buyer_name', 'Mang Tonyo');

        // Rendered for anyone else, the key is absent, not just empty.
        $order = Order::query()->findOrFail($orderId);
        $someoneElse = $this->buyer(['email' => 'someone.else@example.com']);
        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $someoneElse);

        $this->assertArrayNotHasKey('walk_in_buyer_name', (new OrderResource($order))->resolve($request));
    }

    public function test_a_walk_in_appears_in_the_sellers_order_list(): void
    {
        $farmer = $this->farmer(['email' => 'list@example.com']);
        $listing = $this->kamatisListing($farmer, price: 30, quantity: 10);

        $orderId = $this->walkIn($farmer, [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])->assertCreated()->json('data.id');

        $listed = collect($this->asUser($farmer)->getJson('/api/farmer/orders')->assertOk()->json('data'))
            ->firstWhere('id', $orderId);

        $this->assertNotNull($listed);
        $this->assertSame(OrderSource::WalkIn->value, $listed['source']);
    }

    // Helpers. Named apart from CreatesMarketplaceActors so none shadow it.

    private function kamatisListing(User $farmer, float $price, float $quantity): Listing
    {
        $cropType = CropType::factory()->create(['floor_price' => 25, 'max_discount' => 20]);

        return $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => $price,
            'quantity_available' => $quantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function walkIn(User $user, array $body): TestResponse
    {
        return $this->asUser($user)->postJson('/api/farmer/walk-in-sales', $body);
    }
}
