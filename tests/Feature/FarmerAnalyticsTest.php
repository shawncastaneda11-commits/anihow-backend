<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerAnalyticsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_empty_state_returns_zeros(): void
    {
        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.period', 'week')
            ->assertJsonPath('data.summary.completed_orders', 0)
            ->assertJsonPath('data.summary.units_sold', 0)
            ->assertJsonPath('data.summary.gross_sales', 0)
            ->assertJsonPath('data.summary.average_discount', 0)
            ->assertJsonPath('data.walk_in_share.walk_in_orders', 0)
            ->assertJsonPath('data.walk_in_share.app_orders', 0)
            ->assertJsonPath('data.units_per_crop_type', [])
            ->assertJsonPath('data.best_selling', [])
            ->assertJsonPath('data.window_start', now()->startOfDay()->subDays(6)->toDateString())
            ->assertJsonPath('data.window_end', now()->toDateString());
    }

    public function test_only_completed_orders_count_and_walk_ins_are_included(): void
    {
        $farmer = $this->farmer();
        $cropType = CropType::factory()->create(['floor_price' => 25, 'max_discount' => 20]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 40,
        ]);

        $this->placeOrder($this->buyer(), $listing, 2);

        $completed = $this->completeOrder(
            $farmer,
            $this->placeOrder($this->buyer(), $listing, 1),
            30,
        );

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 3,
                'amount_received' => 90,
            ])
            ->assertCreated();

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_orders', 2)
            ->assertJsonPath('data.summary.units_sold', 4)
            ->assertJsonPath('data.summary.gross_sales', 120)
            ->assertJsonPath('data.walk_in_share.walk_in_orders', 1)
            ->assertJsonPath('data.walk_in_share.walk_in_sales', 90)
            ->assertJsonPath('data.walk_in_share.app_orders', 1);
    }

    public function test_another_sellers_orders_are_never_included(): void
    {
        $farm = Farm::factory()->create(['name' => 'Shared farm']);
        $otherFarm = Farm::factory()->create(['name' => 'Other farm']);
        $sellerA = $this->farmer([], $farm);
        $sellerB = $this->farmer([], $farm);
        $sellerC = $this->farmer([], $otherFarm);
        $cropType = CropType::factory()->create(['floor_price' => 25, 'max_discount' => 20]);

        $listingA = $this->listingFor($sellerA, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
        ]);
        $listingB = $this->listingFor($sellerB, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 40,
            'quantity_available' => 20,
        ]);
        $listingC = $this->listingFor($sellerC, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 50,
            'quantity_available' => 20,
        ]);

        $this->completeOrder($sellerA, $this->placeOrder($this->buyer(), $listingA, 1), 30);
        $this->completeOrder($sellerB, $this->placeOrder($this->buyer(), $listingB, 2), 80);
        $this->completeOrder($sellerC, $this->placeOrder($this->buyer(), $listingC, 3), 150);

        $this->asUser($sellerA)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_orders', 1)
            ->assertJsonPath('data.summary.units_sold', 1)
            ->assertJsonPath('data.summary.gross_sales', 30);

        $this->asUser($sellerB)
            ->getJson('/api/farmer/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.completed_orders', 1)
            ->assertJsonPath('data.summary.units_sold', 2)
            ->assertJsonPath('data.summary.gross_sales', 80);
    }

    public function test_a_buyer_and_content_editor_cannot_read_farmer_analytics(): void
    {
        $farm = Farm::factory()->create();
        $editor = User::factory()->create(['farm_id' => $farm->id]);
        $editor->syncRoles(Role::ContentEditor);

        $this->asUser($this->buyer())
            ->getJson('/api/farmer/analytics')
            ->assertForbidden();

        $this->asUser($editor)
            ->getJson('/api/farmer/analytics')
            ->assertForbidden();
    }

    public function test_week_view_bars_match_summary_and_exclude_orders_before_the_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00'));

        $farmer = $this->farmer();
        $listing = $this->pricedListing($farmer, 30);

        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 30);
        $onStart = $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 30);
        $outside = $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 30);

        $this->backdate($onStart, now()->startOfDay()->subDays(6));
        $this->backdate($outside, now()->startOfDay()->subDays(8));

        $response = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?period=week')
            ->assertOk();

        $this->assertSame('2026-09-18', $response->json('data.window_start'));
        $this->assertSame('2026-09-24', $response->json('data.window_end'));
        $this->assertSame(2, $response->json('data.summary.completed_orders'));
        $this->assertEquals(60, $response->json('data.summary.gross_sales'));
        $this->assertBarsMatchSummary($response->json('data'));
    }

    public function test_month_view_bars_match_summary_and_exclude_orders_before_the_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00'));

        $farmer = $this->farmer();
        $listing = $this->pricedListing($farmer, 40);

        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 40);
        $onStart = $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 40);
        $outside = $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 40);

        $this->backdate($onStart, now()->startOfDay()->subDays(29));
        $this->backdate($outside, now()->startOfDay()->subDays(31));

        $response = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?period=month')
            ->assertOk();

        $this->assertSame('2026-08-26', $response->json('data.window_start'));
        $this->assertSame('2026-09-24', $response->json('data.window_end'));
        $this->assertSame(2, $response->json('data.summary.completed_orders'));
        $this->assertEquals(80, $response->json('data.summary.gross_sales'));
        $this->assertBarsMatchSummary($response->json('data'));
        $this->assertSame('2026-08-26', $response->json('data.sales_per_period.0.period'));
    }

    public function test_an_order_completed_at_manila_morning_lands_on_that_manila_day(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));

        Carbon::setTestNow(Carbon::parse('2026-09-24 07:30:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $listing = $this->pricedListing($farmer, 40);
        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 1), 40);

        $response = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?period=week')
            ->assertOk();

        $this->assertSame('2026-09-18', $response->json('data.window_start'));
        $this->assertSame('2026-09-24', $response->json('data.window_end'));

        $today = collect($response->json('data.sales_per_period'))
            ->firstWhere('period', '2026-09-24');

        $this->assertNotNull($today);
        $this->assertSame(1, $today['orders']);
        $this->assertEquals(40, $today['revenue']);
        $this->assertSame(1, $response->json('data.summary.completed_orders'));
        $this->assertEquals(40, $response->json('data.summary.gross_sales'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertBarsMatchSummary(array $payload): void
    {
        $bars = collect($payload['sales_per_period']);

        $this->assertEquals($payload['summary']['gross_sales'], $bars->sum('revenue'));
        $this->assertEquals($payload['summary']['completed_orders'], $bars->sum('orders'));
    }

    private function pricedListing(User $farmer, float $price): Listing
    {
        $cropType = CropType::factory()->create(['floor_price' => 25, 'max_discount' => 20]);

        return $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => $price,
            'quantity_available' => 40,
        ]);
    }

    private function backdate(Order $order, Carbon $completedAt): void
    {
        Order::query()->whereKey($order->id)->update([
            'completed_at' => $completedAt,
        ]);
    }
}
