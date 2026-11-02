<?php

namespace Tests\Feature;

use App\Actions\Exports\ExportYearlyAnalyticsAction;
use App\Enums\ExportType;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Models\CropType;
use App\Models\ExportLog;
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
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\Concerns\ReadsXlsxReports;
use Tests\TestCase;

class YearlyAnalyticsExportTest extends TestCase
{
    use CreatesMarketplaceActors;
    use ReadsXlsxReports;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_permission_migration_grants_export_farm_analytics_only_to_content_editors(): void
    {
        $editor = RoleModel::findByName(Role::ContentEditor->value, 'web');
        $superAdmin = RoleModel::findByName(Role::SuperAdmin->value, 'web');
        $farmer = RoleModel::findByName(Role::FarmerSeller->value, 'web');
        $buyer = RoleModel::findByName(Role::Buyer->value, 'web');

        $this->assertTrue($editor->hasPermissionTo(Permission::ExportFarmAnalytics->value));
        $this->assertFalse($editor->hasPermissionTo(Permission::GenerateExports->value));
        $this->assertFalse($superAdmin->hasPermissionTo(Permission::ExportFarmAnalytics->value));
        $this->assertFalse($farmer->hasPermissionTo(Permission::ExportFarmAnalytics->value));
        $this->assertFalse($buyer->hasPermissionTo(Permission::ExportFarmAnalytics->value));

        $permission = PermissionModel::findByName(Permission::ExportFarmAnalytics->value, 'web');
        $editor->revokePermissionTo($permission);
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $migration = require database_path('migrations/2026_10_10_041142_grant_export_farm_analytics_permission.php');
        $migration->up();

        $editor = RoleModel::findByName(Role::ContentEditor->value, 'web');
        $superAdmin = RoleModel::findByName(Role::SuperAdmin->value, 'web');
        $this->assertTrue($editor->hasPermissionTo(Permission::ExportFarmAnalytics->value));
        $this->assertFalse($superAdmin->hasPermissionTo(Permission::ExportFarmAnalytics->value));

        $migration->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertNull(
            PermissionModel::query()->where('name', Permission::ExportFarmAnalytics->value)->first(),
        );
    }

    public function test_a_super_admin_report_covers_every_section_and_leaves_future_months_blank(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farm = Farm::factory()->create(['name' => 'North Farm']);
        $other = Farm::factory()->create(['name' => 'South Farm']);
        $farmer = $this->farmer([], $farm);
        $otherFarmer = $this->farmer([], $other);
        $crop = $this->cropType(['name' => '=1+1']);
        $listing = $this->listingFor($farmer, ['crop_type_id' => $crop->id]);
        $this->sale($farmer, $crop, $listing, 2, 50, Carbon::parse('2026-06-10 09:00:00', 'Asia/Manila'));
        $this->sale($farmer, $crop, $listing, 1, 80, Carbon::parse('2026-12-10 09:00:00', 'Asia/Manila'));
        $this->sale($otherFarmer, $this->cropType(['name' => 'Other crop']), $this->listingFor($otherFarmer), 1, 30, Carbon::parse('2026-06-11 09:00:00', 'Asia/Manila'));

        HarvestRecord::factory()->create([
            'listing_id' => $listing->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farm->id,
            'crop_type_id' => $crop->id,
            'unit' => 'kg',
            'harvested_on' => '2026-06-02',
            'quantity_harvested' => 4,
            'quantity_rejected' => 0,
            'quantity_good' => 4,
            'price_per_unit' => 10,
            'production_cost' => null,
            'kind' => 'initial',
        ]);

        $response = app(ExportYearlyAnalyticsAction::class)->download($admin, 2026, null, 'all');

        $this->assertStringContainsString('anihow-analytics-2026.xlsx', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('-farm-', (string) $response->headers->get('Content-Disposition'));
        ['sheets' => $sheets, 'formulas' => $formulas] = $this->readXlsx($response);

        $this->assertSame(['Summary', 'Sales over time', 'Top crops', 'Payments & sources', 'Harvest by crop'], array_keys($sheets));
        $this->assertSame('AniHow yearly analytics — All farms', $sheets['Summary'][0][0]);
        $this->assertSame('No cost recorded', $this->summaryValue($sheets, 'Profit so far'));
        $this->assertSame('How buyers paid', $sheets['Payments & sources'][0][0]);
        $this->assertSame('Where sales came from', $this->sheetRow($sheets, 'Payments & sources', 'Where sales came from')[0]);

        $this->assertFalse($formulas);
        $this->assertSame(1, $this->sheetRow($sheets, 'Top crops', '=1+1', 1)[0]);
        $this->assertSame('=1+1', $this->sheetRow($sheets, 'Harvest by crop', '=1+1')[0]);

        $yearly = app(FarmerSalesAnalytics::class)->build(
            $admin,
            AnalyticsRange::fromValues('yearly', null, null, 2026, 'all', true),
        );
        $months = [];

        foreach (array_slice($sheets['Sales over time'], 1, 12) as $row) {
            $months[(string) $row[0]] = $row;
        }

        $this->assertCount(12, $months);

        foreach ($yearly['per_period'] as $bucket) {
            $label = Carbon::parse($bucket['start'])->format('M');
            $row = $months[$label];

            if ($bucket['future']) {
                $this->assertSame([], array_filter(array_slice($row, 1), fn (mixed $cell): bool => $cell !== '' && $cell !== null));

                continue;
            }

            $this->assertEquals($bucket['orders'], $row[1]);
            $this->assertEquals($bucket['sales'], $row[2]);
        }

        $this->assertEquals(80, $yearly['totals']['sales']);
        $this->assertEquals(80, $this->sheetRow($sheets, 'Sales over time', 'Total')[2]);
        $this->assertEquals(40, $this->summaryValue($sheets, 'Value of harvested stock'));
        $this->assertEquals(['Cash', 2, 80, 100], $this->sheetRow($sheets, 'Payments & sources', 'Cash'));
        $this->assertSame('Jun · ₱80.00', $this->summaryValue($sheets, 'Best month'));

        $oneFarm = app(ExportYearlyAnalyticsAction::class)->download($admin, 2026, $farm->id, 'all');
        $this->assertStringContainsString('anihow-analytics-2026-farm-'.$farm->id.'.xlsx', (string) $oneFarm->headers->get('Content-Disposition'));
        $farmSheets = $this->readXlsx($oneFarm)['sheets'];
        $this->assertSame('AniHow yearly analytics — North Farm', $farmSheets['Summary'][0][0]);
        $this->assertEquals(50, $this->summaryValue($farmSheets, 'Total sales'));

        $log = ExportLog::query()->where('type', ExportType::AnalyticsYearly)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(2026, $log->filters['year']);
        $this->assertSame($farm->id, $log->filters['farm_id']);
        $this->assertSame('all', $log->filters['category']);
        $this->assertGreaterThan(0, $log->row_count);
        $this->assertSame('Yearly analytics', $log->type->label());
    }

    public function test_a_content_editor_can_export_only_their_own_farm(): void
    {
        $farm = Farm::factory()->create(['name' => 'Editor Farm', 'value_added_enabled' => true]);
        $other = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $own = $this->farmer([], $farm);
        $elsewhere = $this->farmer([], $other);
        $crop = $this->cropType(['name' => 'Own crop']);
        $this->sale($own, $crop, $this->listingFor($own, ['crop_type_id' => $crop->id]), 1, 40, Carbon::parse('2026-06-03 09:00:00', 'Asia/Manila'));
        $this->sale($elsewhere, $this->cropType(['name' => 'Theirs']), $this->listingFor($elsewhere), 1, 90, Carbon::parse('2026-06-04 09:00:00', 'Asia/Manila'));

        $response = app(ExportYearlyAnalyticsAction::class)->download($editor, 2026, null, 'all');
        $this->assertStringContainsString('anihow-analytics-2026-farm-'.$farm->id.'.xlsx', (string) $response->headers->get('Content-Disposition'));
        $sheets = $this->readXlsx($response)['sheets'];

        $this->assertEquals(40, $this->summaryValue($sheets, 'Total sales'));
        $this->assertStringNotContainsString('Theirs', $this->allText($sheets));
        $this->assertSame($farm->id, ExportLog::query()->first()->filters['farm_id']);

        $before = ExportLog::query()->count();

        try {
            app(ExportYearlyAnalyticsAction::class)->download($editor, 2026, $other->id, 'all');
            $this->fail('A content editor must not export another farm.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame($before, ExportLog::query()->count());

        $unassigned = $this->staff(Role::ContentEditor);

        try {
            app(ExportYearlyAnalyticsAction::class)->download($unassigned, 2026, null, 'all');
            $this->fail('A content editor without a farm must not export.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Livewire::actingAs($editor)
            ->test(Dashboard::class)
            ->mountAction('yearlyReport')
            ->assertFormFieldExists('year')
            ->assertFormFieldDoesNotExist('farm_id');
    }

    public function test_a_farmer_seller_and_a_buyer_cannot_download_the_yearly_report(): void
    {
        foreach ([$this->farmer(), $this->buyer()] as $user) {
            try {
                app(ExportYearlyAnalyticsAction::class)->download($user, 2026, null, 'all');
                $this->fail('Expected a 403.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame(0, ExportLog::query()->count());
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function sale(User $farmer, CropType $crop, Listing $listing, float $quantity, float $lineTotal, Carbon $completedAt): Order
    {
        $order = Order::query()->create([
            'order_number' => 'AH-'.str_pad((string) (Order::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'buyer_id' => $this->buyer()->id,
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'status' => OrderStatus::Completed->value,
            'source' => 'app',
            'fulfillment_preference' => 'buyer_pickup',
            'payment_method' => 'cash_on_handover',
            'subtotal' => $lineTotal,
            'tawad_total' => 0,
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
            'unit_price' => $quantity > 0 ? round($lineTotal / $quantity, 2) : 0,
            'line_subtotal' => $lineTotal,
            'tawad_amount' => 0,
            'line_total' => $lineTotal,
        ]);

        return $order;
    }
}
