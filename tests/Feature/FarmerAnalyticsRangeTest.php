<?php

namespace Tests\Feature;

use App\Enums\HarvestRecordKind;
use App\Enums\HarvestRejectionReason;
use App\Enums\OrderStatus;
use App\Enums\ProductCategory;
use App\Models\CropType;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockRemoval;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerAnalyticsRangeTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_custom_ranges_that_are_too_long_reversed_or_incomplete_are_rejected(): void
    {
        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2025-01-01&to=2026-01-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to' => 'Pick at most 366 days.']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2026-06-01&to=2026-06-16')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2026-06-10&to=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&to=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=yearly&year=2019')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=yearly&year=2027')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=week&category=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2025-01-01&to=2026-01-01')
            ->assertOk()
            ->assertJsonPath('data.range.grouping', 'month');
    }

    public function test_buckets_follow_the_length_of_the_range_and_clip_the_ends(): void
    {
        $farmer = $this->farmer();

        $week = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2026-06-01&to=2026-06-07')
            ->assertOk()
            ->json('data');

        $this->assertSame('day', $week['range']['grouping']);
        $this->assertCount(7, $week['sales']['per_period']);
        $this->assertSame('2026-06-01', $week['sales']['per_period'][0]['start']);
        $this->assertSame('2026-06-07', $week['sales']['per_period'][6]['end']);

        $sixty = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2026-01-07&to=2026-03-07')
            ->assertOk()
            ->json('data.sales.per_period');

        $this->assertSame(60, Carbon::parse('2026-01-07')->diff(Carbon::parse('2026-03-07'))->days + 1);
        $this->assertSame('2026-01-07', $sixty[0]['start']);
        $this->assertSame('2026-01-11', $sixty[0]['end']);
        $this->assertSame('2026-01-12', $sixty[1]['start']);
        $this->assertSame('2026-01-18', $sixty[1]['end']);
        $last = $sixty[array_key_last($sixty)];
        $this->assertSame('2026-03-02', $last['start']);
        $this->assertSame('2026-03-07', $last['end']);

        $months = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=custom&from=2025-01-15&to=2025-08-02')
            ->assertOk()
            ->json('data.sales.per_period');

        $this->assertSame(200, Carbon::parse('2025-01-15')->diff(Carbon::parse('2025-08-02'))->days + 1);
        $this->assertCount(8, $months);
        $this->assertSame('2025-01-15', $months[0]['start']);
        $this->assertSame('2025-01-31', $months[0]['end']);
        $this->assertSame('2025-08-01', $months[7]['start']);
        $this->assertSame('2025-08-02', $months[7]['end']);
    }

    public function test_a_yearly_range_has_twelve_months_and_future_months_stay_zero(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'June crop']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);

        $this->sale($farmer, $crop, $listing, 1, 50, Carbon::parse('2026-06-10 09:00:00', 'Asia/Manila'));
        $this->sale($farmer, $crop, $listing, 1, 80, Carbon::parse('2026-12-10 09:00:00', 'Asia/Manila'));

        $rows = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=yearly&year=2026')
            ->assertOk()
            ->json('data');

        $this->assertSame('month', $rows['range']['grouping']);
        $this->assertCount(12, $rows['sales']['per_period']);
        $this->assertSame('2026-01', $rows['sales']['per_period'][0]['key']);
        $this->assertSame('2026-12', $rows['sales']['per_period'][11]['key']);
        $this->assertFalse($rows['sales']['per_period'][5]['future']);
        $this->assertEquals(50, $rows['sales']['per_period'][5]['sales']);

        foreach (range(6, 11) as $index) {
            $this->assertTrue($rows['sales']['per_period'][$index]['future']);
            $this->assertSame(0, $rows['sales']['per_period'][$index]['orders']);
            $this->assertEquals(0, $rows['sales']['per_period'][$index]['sales']);
        }

        $this->assertEquals(50, $rows['sales']['totals']['sales']);
        $this->assertSame(1, $rows['sales']['totals']['orders']);
        $this->assertSame('2026-06', $rows['sales']['year_total']['best_month']['key']);
        $this->assertEquals(50, $rows['sales']['year_total']['sales']);
        $this->assertSame([2026], $rows['range']['available_years']);
    }

    public function test_manila_midnight_keeps_december_and_january_in_their_own_years(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));

        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Year edge']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);

        $this->sale($farmer, $crop, $listing, 1, 31, Carbon::parse('2025-12-31 23:30:00', 'Asia/Manila'));
        $this->sale($farmer, $crop, $listing, 1, 11, Carbon::parse('2026-01-01 00:30:00', 'Asia/Manila'));

        $previous = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=yearly&year=2025')
            ->assertOk()
            ->json('data');

        $december = collect($previous['sales']['per_period'])->firstWhere('key', '2025-12');
        $this->assertSame(1, $december['orders']);
        $this->assertEquals(31, $december['sales']);
        $this->assertEquals(31, $previous['sales']['totals']['sales']);
        $this->assertSame([2025, 2026], $previous['range']['available_years']);

        $current = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=yearly&year=2026')
            ->assertOk()
            ->json('data.sales');

        $january = collect($current['per_period'])->firstWhere('key', '2026-01');
        $this->assertSame(1, $january['orders']);
        $this->assertEquals(11, $january['sales']);
        $this->assertEquals(11, $current['totals']['sales']);
    }

    public function test_pie_counts_completed_lines_after_tawad_and_splits_the_rest(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $amounts = [
            'Alpha' => 80,
            'Bravo' => 70,
            'Charlie' => 60,
            'Delta' => 50,
            'Echo' => 40,
            'Foxtrot' => 30,
            'Golf' => 25,
        ];

        foreach ($amounts as $name => $total) {
            $crop = $this->cropType(['name' => $name]);
            $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);
            $tawad = $name === 'Golf' ? 5.0 : 0.0;
            $source = $name === 'Foxtrot' ? 'walk_in' : 'app';
            $payment = $name === 'Alpha' ? 'online_transfer' : 'cash_on_handover';
            $this->sale($farmer, $crop, $listing, 1, $total, now(), $tawad, $payment, $source, buyer: $buyer);
        }

        $extra = $this->cropType(['name' => 'Alpha']);
        $extraListing = $this->listingFor($farmer, ['crop_type_id' => $extra->id]);
        $this->sale($farmer, $extra, $extraListing, 1, 99, now(), status: OrderStatus::Placed->value, buyer: $buyer);
        $this->sale($farmer, $extra, $extraListing, 1, 99, now(), status: OrderStatus::Cancelled->value, buyer: $buyer);
        $this->sale($farmer, $extra, $extraListing, 1, 99, Carbon::parse('2026-06-16 08:00:00', 'Asia/Manila'), buyer: $buyer);

        $sales = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month')
            ->assertOk()
            ->json('data.sales');

        $this->assertEquals(355, $sales['totals']['sales']);
        $this->assertSame(7, $sales['totals']['orders']);
        $this->assertEquals(5, $sales['totals']['tawad_total']);
        $this->assertEquals(round(355 / 7, 2), $sales['totals']['average_order']);
        $this->assertEquals(round(5 / 7, 2), $sales['totals']['average_tawad']);

        $this->assertCount(5, $sales['pie']['slices']);
        $this->assertSame(2, $sales['pie']['others']['crops']);
        $this->assertEquals(55, $sales['pie']['others']['sales']);
        $sliceSales = collect($sales['pie']['slices'])->sum('sales');
        $this->assertEquals($sales['totals']['sales'], $sliceSales + $sales['pie']['others']['sales']);
        $this->assertEquals(25, collect($sales['top_crops'])->firstWhere('crop', 'Golf')['sales']);

        $this->assertEquals(80, $sales['payment_split']['online']['sales']);
        $this->assertSame(1, $sales['payment_split']['online']['orders']);
        $this->assertEquals(275, $sales['payment_split']['cash']['sales']);
        $this->assertSame(6, $sales['payment_split']['cash']['orders']);
        $this->assertEquals($sales['totals']['sales'], $sales['payment_split']['online']['sales'] + $sales['payment_split']['cash']['sales']);
        $this->assertSame($sales['totals']['orders'], $sales['payment_split']['online']['orders'] + $sales['payment_split']['cash']['orders']);

        $this->assertEquals(30, $sales['source_split']['walk_in']['sales']);
        $this->assertSame(1, $sales['source_split']['walk_in']['orders']);
        $this->assertEquals(325, $sales['source_split']['app']['sales']);
        $this->assertEquals($sales['totals']['sales'], $sales['source_split']['app']['sales'] + $sales['source_split']['walk_in']['sales']);
        $this->assertSame($sales['totals']['orders'], $sales['source_split']['app']['orders'] + $sales['source_split']['walk_in']['orders']);
        $this->assertNull($sales['year_total']);

        $lineTotal = (float) OrderItem::query()
            ->whereIn('order_id', Order::query()->where('status', OrderStatus::Completed)->pluck('id'))
            ->sum('line_total');
        $orderTotal = (float) Order::query()->where('status', OrderStatus::Completed)->sum('total');
        $this->assertEquals($orderTotal, $lineTotal);
        $this->assertEquals($lineTotal, $sales['totals']['sales'] + 99);
    }

    public function test_top_crops_merge_grams_into_kilograms_and_keep_trays_separate(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Mango']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id, 'unit' => 'kg']);
        $order = $this->sale($farmer, $crop, $listing, 1, 100, now(), unit: 'kg');
        $order->items()->create([
            'listing_id' => $listing->id,
            'crop_type_id' => $crop->id,
            'listing_name' => 'Mango',
            'unit' => 'g',
            'quantity' => 500,
            'unit_price' => 0.04,
            'line_subtotal' => 20,
            'tawad_amount' => 0,
            'line_total' => 20,
        ]);
        $order->forceFill([
            'subtotal' => 120,
            'total' => 120,
        ])->save();
        $tray = $this->sale($farmer, $crop, $listing, 2, 40, now(), unit: 'tray');

        $this->assertNotNull($tray);

        $rows = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month')
            ->assertOk()
            ->json('data.sales.top_crops');

        $this->assertCount(2, $rows);
        $this->assertSame('kg', $rows[0]['unit']);
        $this->assertEquals(1.5, $rows[0]['quantity']);
        $this->assertEquals(120, $rows[0]['sales']);
        $this->assertSame('tray', $rows[1]['unit']);
        $this->assertEquals(2, $rows[1]['quantity']);
        $this->assertEquals(40, $rows[1]['sales']);
    }

    public function test_harvest_attributes_sold_waiting_and_removals_by_each_records_share(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Pechay']);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'price_per_unit' => 25,
            'quantity_available' => 200,
            'stock_tracked_since' => Carbon::parse('2026-01-01 00:00:00', 'Asia/Manila'),
        ]);

        $this->harvest($farmer, $listing, $crop, '2026-06-05', 60, 6, HarvestRejectionReason::Pests->value, price: 25);
        $this->harvest($farmer, $listing, $crop, '2026-05-01', 40, price: 25);
        $this->sale($farmer, $crop, $listing, 50, 1000, Carbon::parse('2026-06-08 10:00:00', 'Asia/Manila'), confirmedAt: Carbon::parse('2026-06-08 09:00:00', 'Asia/Manila'));
        $this->sale($farmer, $crop, $listing, 10, 200, Carbon::parse('2026-06-09 10:00:00', 'Asia/Manila'), status: OrderStatus::Confirmed->value, confirmedAt: Carbon::parse('2026-06-09 09:00:00', 'Asia/Manila'));
        StockRemoval::factory()->create([
            'listing_id' => $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'quantity' => 5,
            'reason' => 'spoiled',
        ]);
        StockRemoval::factory()->create([
            'listing_id' => $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'quantity' => 2,
            'reason' => 'damaged',
        ]);
        $listing->forceFill(['price_per_unit' => 99])->save();

        $openingCrop = $this->cropType(['name' => 'Opening crop']);
        $openingListing = $this->listingFor($farmer, ['crop_type_id' => $openingCrop->id]);
        $this->harvest($farmer, $openingListing, $openingCrop, '2026-06-05', 100, kind: HarvestRecordKind::Opening->value);

        $okra = $this->cropType(['name' => 'Okra']);
        $okraListing = $this->listingFor($farmer, ['crop_type_id' => $okra->id, 'stock_tracked_since' => now()]);
        $this->harvest($farmer, $okraListing, $okra, '2026-06-04', 10, kind: HarvestRecordKind::Estimated->value, price: 8);

        $loose = $this->cropType(['name' => 'Loose']);
        $this->harvest($farmer, $listing, $loose, '2026-06-03', 4, 1, HarvestRejectionReason::Spoiled->value, price: 10, unlinked: true);

        $overflowCrop = $this->cropType(['name' => 'Overflow']);
        $overflow = $this->listingFor($farmer, [
            'crop_type_id' => $overflowCrop->id,
            'stock_tracked_since' => Carbon::parse('2026-01-01', 'Asia/Manila'),
        ]);
        $this->harvest($farmer, $overflow, $overflowCrop, '2026-06-02', 5, price: 10);
        $this->sale($farmer, $overflowCrop, $overflow, 20, 100, now(), confirmedAt: now());

        $harvest = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month')
            ->assertOk()
            ->json('data.harvest');

        $this->assertSame(4, $harvest['records']);
        $this->assertSame(1, $harvest['estimated_records']);
        $this->assertSame(1, $harvest['unlinked_records']);
        $this->assertNull(collect($harvest['crops'])->firstWhere('crop', 'Opening crop'));

        $pechay = collect($harvest['crops'])->firstWhere('crop', 'Pechay');
        $this->assertEquals(66, $pechay['harvested']);
        $this->assertEquals(6, $pechay['rejected']);
        $this->assertEquals(60, $pechay['good']);
        $this->assertEquals(6, $pechay['rejected_by_reason']['pests']);
        $this->assertEquals(30, $pechay['sold']);
        $this->assertEquals(6, $pechay['waiting']);
        $this->assertEquals(4.2, $pechay['removed']);
        $this->assertEquals(3, $pechay['removed_by_reason']['spoiled']);
        $this->assertEquals(1.2, $pechay['removed_by_reason']['damaged']);
        $this->assertEquals(0, $pechay['removed_by_reason']['sold_outside']);
        $this->assertEquals(0, $pechay['removed_by_reason']['correction']);
        $this->assertEquals(19.8, $pechay['remaining']);
        $this->assertEquals(1500, $pechay['potential_income']);
        $this->assertEquals(600, $pechay['actual_income']);

        $overflowRow = collect($harvest['crops'])->firstWhere('crop', 'Overflow');
        $this->assertEquals(0, $overflowRow['remaining']);
        $this->assertEquals(20, $overflowRow['sold']);

        $looseRow = collect($harvest['crops'])->firstWhere('crop', 'Loose');
        $this->assertEquals(0, $looseRow['sold']);
        $this->assertEquals(40, $looseRow['potential_income']);
        $this->assertSame(1, $harvest['estimated_records']);
    }

    public function test_cost_totals_ignore_records_without_a_cost_and_never_use_zero_as_empty(): void
    {
        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Costed']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);

        $empty = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month')
            ->assertOk()
            ->json('data.harvest.cost');

        $this->assertNull($empty['cost_total']);
        $this->assertNull($empty['potential_profit']);
        $this->assertNull($empty['actual_profit']);
        $this->assertSame(0, $empty['records_with_cost']);

        $this->harvest($farmer, $listing, $crop, '2026-06-05', 10, price: 10, cost: null);
        $this->harvest($farmer, $listing, $crop, '2026-06-06', 10, price: 20, cost: 40);

        $cost = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month')
            ->assertOk()
            ->json('data.harvest.cost');

        $this->assertSame(1, $cost['records_with_cost']);
        $this->assertSame(1, $cost['records_without_cost']);
        $this->assertEquals(40, $cost['cost_total']);
        $this->assertEquals(200, $cost['potential_income_with_cost']);
        $this->assertEquals(0, $cost['actual_income_with_cost']);
        $this->assertEquals(160, $cost['potential_profit']);
        $this->assertEquals(-40, $cost['actual_profit']);
    }

    public function test_fresh_and_value_added_split_sales_and_harvest(): void
    {
        $farmer = $this->farmer();
        $fresh = $this->cropType(['name' => 'Fresh pechay', 'category' => ProductCategory::FreshProduce->value]);
        $added = $this->cropType(['name' => 'Atchara', 'category' => ProductCategory::ValueAdded->value]);
        $freshListing = $this->listingFor($farmer, ['crop_type_id' => $fresh->id]);
        $addedListing = $this->listingFor($farmer, ['crop_type_id' => $added->id]);

        $this->sale($farmer, $fresh, $freshListing, 1, 100, now());
        $this->sale($farmer, $added, $addedListing, 1, 40, now());
        $this->harvest($farmer, $freshListing, $fresh, '2026-06-05', 12);
        $this->harvest($farmer, $addedListing, $added, '2026-06-05', 7, valueAdded: true);

        $freshRows = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month&category=fresh')
            ->assertOk()
            ->json('data');
        $this->assertEquals(100, $freshRows['sales']['totals']['sales']);
        $this->assertEquals(12, collect($freshRows['harvest']['crops'])->sum('good'));

        $addedRows = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=month&category=value_added')
            ->assertOk()
            ->json('data');
        $this->assertEquals(40, $addedRows['sales']['totals']['sales']);
        $this->assertEquals(7, collect($addedRows['harvest']['crops'])->sum('good'));
    }

    public function test_another_sellers_orders_and_harvests_are_excluded(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer();
        $crop = $this->cropType(['name' => 'Secret']);
        $listing = $this->listingFor($other, ['crop_type_id' => $crop->id, 'stock_tracked_since' => now()->subDay()]);
        $this->sale($other, $crop, $listing, 3, 900, now());
        $this->harvest($other, $listing, $crop, '2026-06-05', 40);

        $rows = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=year')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $rows['sales']['totals']['sales']);
        $this->assertSame(0, $rows['sales']['totals']['orders']);
        $this->assertSame(0, $rows['harvest']['records']);
        $this->assertSame([], $rows['harvest']['crops']);
    }

    public function test_period_week_keeps_the_rolling_window_and_omits_the_new_blocks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Week crop']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);

        $this->sale($farmer, $crop, $listing, 1, 30, now());
        $this->sale($farmer, $crop, $listing, 1, 30, now()->startOfDay()->subDays(6));
        $this->sale($farmer, $crop, $listing, 1, 30, now()->startOfDay()->subDays(8));

        $response = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?period=week')
            ->assertOk();

        $payload = $response->json('data');
        $this->assertSame([
            'period',
            'window_start',
            'window_end',
            'summary',
            'sales_per_period',
            'units_per_crop_type',
            'best_selling',
            'walk_in_share',
        ], array_keys($payload));
        $this->assertSame('week', $payload['period']);
        $this->assertSame('2026-09-18', $payload['window_start']);
        $this->assertSame('2026-09-24', $payload['window_end']);
        $this->assertSame(2, $payload['summary']['completed_orders']);
        $this->assertEquals(60, $payload['summary']['gross_sales']);
        $this->assertEquals(60, collect($payload['sales_per_period'])->sum('revenue'));
        $this->assertSame('2026-09-18', $payload['sales_per_period'][0]['period']);

        $ranged = $this->asUser($farmer)
            ->getJson('/api/farmer/analytics?range=week')
            ->assertOk()
            ->json('data');

        $this->assertSame('2026-09-21', $ranged['window_start']);
        $this->assertSame('2026-09-24', $ranged['window_end']);
        $this->assertSame(1, $ranged['summary']['completed_orders']);
        $this->assertEquals(30, $ranged['summary']['gross_sales']);
        $this->assertArrayHasKey('sales', $ranged);
        $this->assertArrayHasKey('harvest', $ranged);
    }

    private function sale(
        User $farmer,
        CropType $crop,
        Listing $listing,
        float $quantity,
        float $lineTotal,
        Carbon $completedAt,
        float $tawad = 0,
        string $payment = 'cash_on_handover',
        string $source = 'app',
        string $unit = 'kg',
        string $status = 'completed',
        ?Carbon $confirmedAt = null,
        ?User $buyer = null,
    ): Order {
        $confirmedAt ??= $completedAt;
        $order = Order::query()->create([
            'order_number' => 'AH-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'buyer_id' => $source === 'walk_in' ? null : ($buyer ?? $this->buyer())->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'status' => $status,
            'source' => $source,
            'fulfillment_preference' => 'buyer_pickup',
            'payment_method' => $payment,
            'subtotal' => $lineTotal + $tawad,
            'tawad_total' => $tawad,
            'total' => $lineTotal,
            'amount_received' => $status === OrderStatus::Completed->value ? $lineTotal : null,
            'confirmed_at' => $status === OrderStatus::Placed->value ? null : $confirmedAt,
            'completed_at' => $status === OrderStatus::Completed->value ? $completedAt : null,
        ]);

        $order->items()->create([
            'listing_id' => $listing->id,
            'crop_type_id' => $crop->id,
            'listing_name' => $crop->name,
            'unit' => $unit,
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? round(($lineTotal + $tawad) / $quantity, 2) : 0,
            'line_subtotal' => $lineTotal + $tawad,
            'tawad_amount' => $tawad,
            'line_total' => $lineTotal,
        ]);

        return $order;
    }

    private function harvest(
        User $farmer,
        Listing $listing,
        CropType $crop,
        string $on,
        float $good,
        float $rejected = 0,
        ?string $reason = null,
        string $kind = 'initial',
        float $price = 25,
        ?float $cost = null,
        bool $unlinked = false,
        bool $valueAdded = false,
    ): HarvestRecord {
        return HarvestRecord::factory()->create([
            'listing_id' => $unlinked ? null : $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'is_value_added' => $valueAdded,
            'harvested_on' => $on,
            'quantity_harvested' => $good + $rejected,
            'quantity_rejected' => $rejected,
            'quantity_good' => $good,
            'rejection_reason' => $reason,
            'price_per_unit' => $price,
            'production_cost' => $cost,
            'kind' => $kind,
        ]);
    }
}
