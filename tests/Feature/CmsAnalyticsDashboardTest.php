<?php

namespace Tests\Feature;

use App\Enums\HarvestRecordKind;
use App\Enums\HarvestRejectionReason;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BestSellersChart;
use App\Filament\Widgets\HarvestByCropTable;
use App\Filament\Widgets\HarvestSummaryStats;
use App\Filament\Widgets\PaymentSplitStats;
use App\Filament\Widgets\SalesOverTimeChart;
use App\Filament\Widgets\SalesSummaryStats;
use App\Filament\Widgets\TopCropsTable;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Services\FarmerSalesAnalytics;
use App\Support\Analytics\AnalyticsRange;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CmsAnalyticsDashboardTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ranges_clamp_to_today_and_366_days_and_a_known_year(): void
    {
        $long = AnalyticsRange::fromValues('custom', '2025-01-01', '2026-10-10', null, 'all', true);
        $this->assertSame('2025-10-10', $long->from->toDateString());
        $this->assertSame('2026-10-10', $long->to->toDateString());
        $this->assertSame(366, AnalyticsRange::inclusiveDays($long->from, $long->to));

        $future = AnalyticsRange::fromValues('custom', '2026-10-01', '2026-12-31', null, 'all', true);
        $this->assertSame('2026-10-01', $future->from->toDateString());
        $this->assertSame('2026-10-10', $future->to->toDateString());

        $unknown = AnalyticsRange::fromValues('bogus', null, null, null, 'all', true);
        $this->assertSame('month', $unknown->key);

        $year = AnalyticsRange::fromValues('yearly', null, null, 1999, 'all', true);
        $this->assertSame(2026, $year->year);
    }

    public function test_a_super_admin_sees_every_farm_until_the_farm_filter_is_set(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $first = $this->farmer(['name' => 'Seller Unique Name']);
        $second = $this->farmer();
        $this->sell($first, 40, Carbon::parse('2026-10-03 09:00:00', 'Asia/Manila'));
        $this->sell($second, 90, Carbon::parse('2026-10-04 09:00:00', 'Asia/Manila'));

        $built = app(FarmerSalesAnalytics::class)->build(
            $admin,
            AnalyticsRange::fromValues('month', null, null, null, 'all', true),
        );

        Livewire::actingAs($admin)
            ->test(SalesSummaryStats::class, ['pageFilters' => ['range' => 'month', 'category' => 'all']])
            ->assertSee('₱'.number_format($built['totals']['sales'], 2))
            ->assertSee('Oct 1 – Oct 10, 2026')
            ->assertDontSee('Seller Unique Name');

        Livewire::actingAs($admin)
            ->test(SalesSummaryStats::class, [
                'pageFilters' => ['range' => 'month', 'category' => 'all', 'farm_id' => $first->farm_id],
            ])
            ->assertSee('₱40.00')
            ->assertDontSee('₱90.00')
            ->assertDontSee('₱130.00');
    }

    public function test_a_content_editor_stays_on_their_farm_when_another_farm_is_forced(): void
    {
        $farm = Farm::factory()->create();
        $other = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $own = $this->farmer([], $farm);
        $elsewhere = $this->farmer([], $other);
        $this->sell($own, 40, Carbon::parse('2026-10-03 09:00:00', 'Asia/Manila'));
        $this->sell($elsewhere, 90, Carbon::parse('2026-10-04 09:00:00', 'Asia/Manila'));

        Livewire::actingAs($editor)
            ->test(SalesSummaryStats::class, [
                'pageFilters' => ['range' => 'month', 'category' => 'all', 'farm_id' => $other->id],
            ])
            ->assertSee('₱40.00')
            ->assertDontSee('₱90.00');
    }

    public function test_a_farmer_seller_and_a_buyer_cannot_view_the_dashboard_widgets(): void
    {
        $this->actingAs($this->farmer());

        foreach ($this->widgets() as $widget) {
            $this->assertFalse($widget::canView());
        }

        $this->actingAs($this->buyer());

        foreach ($this->widgets() as $widget) {
            $this->assertFalse($widget::canView());
        }
    }

    public function test_widgets_match_the_analytics_services_and_show_empty_states(): void
    {
        $admin = $this->staff(Role::SuperAdmin);

        Livewire::actingAs($admin)
            ->test(SalesSummaryStats::class, ['pageFilters' => ['range' => 'month']])
            ->assertSee('No completed sales in this period.');

        Livewire::actingAs($admin)
            ->test(HarvestSummaryStats::class, ['pageFilters' => ['range' => 'month']])
            ->assertSee('No harvests recorded in this period.');

        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Pechay']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);
        $this->sale($farmer, $crop, $listing, 2, 40, Carbon::parse('2026-10-03 09:00:00', 'Asia/Manila'), tawad: 5, payment: 'online_transfer');
        $this->sale($farmer, $crop, $listing, 1, 25, Carbon::parse('2026-10-04 09:00:00', 'Asia/Manila'), source: 'walk_in');

        $built = app(FarmerSalesAnalytics::class)->build(
            $admin,
            AnalyticsRange::fromValues('month', null, null, null, 'all', true),
        );

        Livewire::actingAs($admin)
            ->test(SalesSummaryStats::class, ['pageFilters' => ['range' => 'month', 'category' => 'all']])
            ->assertSee('₱'.number_format($built['totals']['sales'], 2))
            ->assertSee((string) $built['totals']['orders'])
            ->assertSee('₱'.number_format($built['totals']['tawad_total'], 2).' tawad in total');

        Livewire::actingAs($admin)
            ->test(PaymentSplitStats::class, ['pageFilters' => ['range' => 'month', 'category' => 'all']])
            ->assertSee('₱40.00')
            ->assertSee('includes walk-in sales')
            ->assertSee('₱25.00');

        $chart = Livewire::actingAs($admin)
            ->test(SalesOverTimeChart::class, [
                'pageFilters' => [
                    'range' => 'custom',
                    'from' => '2026-10-01',
                    'to' => '2026-12-31',
                    'category' => 'all',
                ],
            ])
            ->instance();
        $data = (new ReflectionMethod($chart, 'getData'))->invoke($chart);

        $this->assertContains('Oct 3', $data['labels']);
        $this->assertEquals(0, $data['datasets'][0]['data'][array_search('Oct 10', $data['labels'], true)]);

        $yearlyChart = Livewire::actingAs($admin)
            ->test(SalesOverTimeChart::class, [
                'pageFilters' => ['range' => 'yearly', 'year' => 2026, 'category' => 'all'],
            ])
            ->instance();
        $yearlyData = (new ReflectionMethod($yearlyChart, 'getData'))->invoke($yearlyChart);
        $november = array_search('Nov', $yearlyData['labels'], true);

        $this->assertNotFalse($november);
        $this->assertEquals(0, $yearlyData['datasets'][0]['data'][$november]);

        Livewire::actingAs($admin)
            ->test(TopCropsTable::class, ['pageFilters' => ['range' => 'month', 'category' => 'all']])
            ->assertSee('Pechay')
            ->assertSee('₱65.00');
    }

    public function test_best_sellers_keep_five_colors_and_an_others_slice(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();

        foreach (range(1, 6) as $index) {
            $crop = $this->cropType(['name' => 'Crop '.$index]);
            $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);
            $this->sale($farmer, $crop, $listing, 1, 10 * $index, Carbon::parse('2026-10-0'.$index.' 09:00:00', 'Asia/Manila'));
        }

        $chart = Livewire::actingAs($admin)
            ->test(BestSellersChart::class, ['pageFilters' => ['range' => 'month', 'category' => 'all']])
            ->instance();
        $data = (new ReflectionMethod($chart, 'getData'))->invoke($chart);

        $this->assertContains('Others (1 crops)', $data['labels']);
        $this->assertSame('#58A67D', $data['datasets'][0]['backgroundColor'][0]);
        $this->assertSame('#8C948F', $data['datasets'][0]['backgroundColor'][5]);
    }

    public function test_harvest_stats_hide_a_missing_cost_and_show_a_loss(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $crop = $this->cropType(['name' => 'Tomato']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);

        HarvestRecord::factory()->create([
            'listing_id' => $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'harvested_on' => '2026-10-02',
            'quantity_harvested' => 15,
            'quantity_rejected' => 5,
            'quantity_good' => 10,
            'rejection_reason' => HarvestRejectionReason::Spoiled->value,
            'price_per_unit' => 20,
            'production_cost' => null,
            'kind' => HarvestRecordKind::Estimated,
        ]);

        Livewire::actingAs($admin)
            ->test(HarvestSummaryStats::class, ['pageFilters' => ['range' => 'month']])
            ->assertSee('Profit — No cost recorded')
            ->assertSee('1 estimated')
            ->assertDontSee('Expected profit');

        Livewire::actingAs($admin)
            ->test(HarvestByCropTable::class, ['pageFilters' => ['range' => 'month']])
            ->assertSee('Tomato')
            ->assertSee('Rejected: Spoiled 5 kg');

        $costly = $this->cropType(['name' => 'Loss crop']);
        $costlyListing = $this->listingFor($farmer, ['crop_type_id' => $costly->id]);
        HarvestRecord::factory()->create([
            'listing_id' => $costlyListing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'crop_type_id' => $costly->id,
            'unit' => 'kg',
            'harvested_on' => '2026-10-02',
            'quantity_harvested' => 1,
            'quantity_rejected' => 0,
            'quantity_good' => 1,
            'price_per_unit' => 0,
            'production_cost' => 1633.33,
            'kind' => HarvestRecordKind::Initial,
        ]);

        Livewire::actingAs($admin)
            ->test(HarvestSummaryStats::class, ['pageFilters' => ['range' => 'month']])
            ->assertSee('−₱1,633.33')
            ->assertSee('Based on 1 of 2 harvests with a cost');
    }

    public function test_category_is_hidden_when_the_editors_farm_has_value_added_off(): void
    {
        $farm = Farm::factory()->create(['value_added_enabled' => false]);
        $editor = $this->staff(Role::ContentEditor, $farm);
        $admin = $this->staff(Role::SuperAdmin);

        $editorFields = $this->filterVisibility($editor);
        $this->assertFalse($editorFields['category']);
        $this->assertTrue($editorFields['own_farm']);
        $this->assertFalse($editorFields['farm_id']);

        $adminFields = $this->filterVisibility($admin);
        $this->assertTrue($adminFields['category']);
        $this->assertTrue($adminFields['farm_id']);
        $this->assertFalse($adminFields['own_farm']);

        Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->assertSee($farm->name)
            ->assertDontSee('All farms');

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee('All farms')
            ->assertSee('This month');
    }

    public function test_the_old_export_stays_on_the_dashboard_and_yearly_csv_follows_the_role(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create(['value_added_enabled' => false]));
        $unassigned = $this->staff(Role::ContentEditor);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertActionVisible('exportCsv')
            ->assertActionVisible('yearlyCsv')
            ->mountAction('yearlyCsv')
            ->assertFormFieldExists('year')
            ->assertFormFieldExists('farm_id')
            ->assertFormFieldExists('category');

        Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->assertActionHidden('exportCsv')
            ->assertActionVisible('yearlyCsv')
            ->mountAction('yearlyCsv')
            ->assertFormFieldExists('year')
            ->assertFormFieldDoesNotExist('farm_id')
            ->assertFormFieldDoesNotExist('category');

        Livewire::actingAs($unassigned)
            ->test(Dashboard::class)
            ->assertActionHidden('yearlyCsv');

        $this->assertTrue($editor->can(Permission::ExportFarmAnalytics->value));
        $this->assertFalse($editor->can(Permission::GenerateExports->value));
    }

    /**
     * @return list<class-string>
     */
    private function widgets(): array
    {
        return [
            SalesSummaryStats::class,
            SalesOverTimeChart::class,
            BestSellersChart::class,
            TopCropsTable::class,
            PaymentSplitStats::class,
            HarvestSummaryStats::class,
            HarvestByCropTable::class,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function filterVisibility(User $user): array
    {
        $form = Livewire::actingAs($user)->test(Dashboard::class)->instance()->getFiltersForm();
        $visible = [];

        foreach ($form->getFlatFields(withHidden: true) as $field) {
            $visible[$field->getName()] = $field->isVisible();
        }

        return $visible;
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function sell(User $farmer, float $lineTotal, Carbon $completedAt): Order
    {
        $crop = $this->cropType();
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);

        return $this->sale($farmer, $crop, $listing, 1, $lineTotal, $completedAt);
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
    ): Order {
        $order = Order::query()->create([
            'order_number' => 'AH-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'buyer_id' => $source === 'walk_in' ? null : $this->buyer()->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'status' => OrderStatus::Completed->value,
            'source' => $source,
            'fulfillment_preference' => 'buyer_pickup',
            'payment_method' => $payment,
            'subtotal' => $lineTotal + $tawad,
            'tawad_total' => $tawad,
            'total' => $lineTotal,
            'amount_received' => $lineTotal,
            'confirmed_at' => $completedAt,
            'completed_at' => $completedAt,
        ]);

        $order->items()->create([
            'listing_id' => $listing->id,
            'crop_type_id' => $crop->id,
            'listing_name' => $crop->name,
            'unit' => 'kg',
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? round(($lineTotal + $tawad) / $quantity, 2) : 0,
            'line_subtotal' => $lineTotal + $tawad,
            'tawad_amount' => $tawad,
            'line_total' => $lineTotal,
        ]);

        return $order;
    }
}
