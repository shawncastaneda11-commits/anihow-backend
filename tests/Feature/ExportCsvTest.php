<?php

namespace Tests\Feature;

use App\Actions\Exports\ExportAnalyticsAction;
use App\Actions\Exports\ExportOrderLedgerAction;
use App\Enums\ExportType;
use App\Enums\Role;
use App\Filament\Resources\ExportLogs\ExportLogResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\ExportLog;
use App\Models\Farm;
use App\Models\Order;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ExportCsvTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-24 15:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_can_download_the_order_ledger_csv(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer(['shop_name' => 'Mang Tonyo Stall', 'email' => 'seller-secret@example.com']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer([
            'name' => 'Ana Buyer',
            'email' => 'ana-secret@example.com',
            'phone' => '09171234567',
        ]);
        $order = $this->completeOrder($farmer, $this->placeOrder($buyer, $listing, 1), 30);

        $response = app(ExportOrderLedgerAction::class)->download($admin, Order::query());
        $csv = $this->csvBody($response);

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('anihow-orders-2026-09-24.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(implode(',', ExportOrderLedgerAction::COLUMNS), $csv);
        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringContainsString('Mang Tonyo Stall', $csv);
        $this->assertStringContainsString('Ana Buyer', $csv);
        $this->assertStringNotContainsString('ana-secret@example.com', $csv);
        $this->assertStringNotContainsString('09171234567', $csv);
        $this->assertStringNotContainsString('seller-secret@example.com', $csv);
        $this->assertStringNotContainsString((string) $buyer->phone, $csv);

        $this->assertSame(1, ExportLog::query()->count());
        $this->assertSame(ExportType::OrderLedger, ExportLog::query()->first()->type);
        $this->assertSame(1, ExportLog::query()->first()->row_count);
        $this->assertSame($admin->id, ExportLog::query()->first()->user_id);
    }

    public function test_order_ledger_export_respects_farm_and_status_filters(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmA = Farm::factory()->create(['name' => 'Farm A']);
        $farmB = Farm::factory()->create(['name' => 'Farm B']);
        $sellerA = $this->farmer(['shop_name' => 'Stall A'], $farmA);
        $sellerB = $this->farmer(['shop_name' => 'Stall B'], $farmB);
        $listingA = $this->listingFor($sellerA, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $listingB = $this->listingFor($sellerB, ['price_per_unit' => 40, 'quantity_available' => 10]);

        $completedA = $this->completeOrder($sellerA, $this->placeOrder($this->buyer(), $listingA, 1), 30);
        $placedB = $this->placeOrder($this->buyer(), $listingB, 1);

        $filtered = Order::query()
            ->where('farm_id', $farmA->id)
            ->where('status', 'completed');

        $csv = $this->csvBody(app(ExportOrderLedgerAction::class)->download(
            $admin,
            $filtered,
            ['farm' => $farmA->id, 'status' => 'completed'],
        ));

        $this->assertStringContainsString($completedA->order_number, $csv);
        $this->assertStringNotContainsString($placedB->order_number, $csv);
        $this->assertStringContainsString('Stall A', $csv);
        $this->assertStringNotContainsString('Stall B', $csv);
        $this->assertSame(['farm' => $farmA->id, 'status' => 'completed'], ExportLog::query()->first()->filters);
    }

    public function test_walk_in_buyer_label_is_exported_and_app_buyer_contact_is_not(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer(['shop_name' => 'Walk-in Stall']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);

        $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $listing->id,
                'quantity' => 1,
                'amount_received' => 30,
                'buyer_name' => 'Mang Tonyo',
            ])
            ->assertCreated();

        $csv = $this->csvBody(app(ExportOrderLedgerAction::class)->download($admin, Order::query()));

        $this->assertStringContainsString('walk_in', $csv);
        $this->assertStringContainsString('Mang Tonyo', $csv);
        $this->assertStringNotContainsString($farmer->email, $csv);
    }

    public function test_super_admin_can_download_analytics_csv_matching_the_service(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer(['shop_name' => 'Analytics Stall']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 40, 'quantity_available' => 10]);
        $this->completeOrder($farmer, $this->placeOrder($this->buyer(), $listing, 2), 80);

        $since = now()->startOfDay()->subDays(29);
        $until = now()->endOfDay();
        $analytics = app(AnalyticsService::class);

        $csv = $this->csvBody(app(ExportAnalyticsAction::class)->download($admin, $since, $until));

        $this->assertStringContainsString('anihow-analytics-2026-09-24.csv', (string) $this->lastDisposition);
        $this->assertStringContainsString('Units sold per crop type', $csv);
        $this->assertStringContainsString('Sales per period', $csv);
        $this->assertStringContainsString('Best-selling produce', $csv);
        $this->assertStringContainsString('Average discount', $csv);

        $units = $analytics->unitsSoldPerCropType($admin, since: $since, until: $until);
        $sales = $analytics->salesPerPeriod($admin, 'day', since: $since, until: $until);
        $discount = $analytics->averageDiscount($admin, since: $since, until: $until);

        $this->assertEquals($units->sum('units'), $this->sectionSum($csv, 'Units sold per crop type', 2));
        $this->assertEquals($units->sum('revenue'), $this->sectionSum($csv, 'Units sold per crop type', 3));
        $this->assertEquals($sales->sum('revenue'), $this->sectionSum($csv, 'Sales per period', 2));
        $this->assertEquals($sales->sum('orders'), $this->sectionSum($csv, 'Sales per period', 1));
        $this->assertStringContainsString((string) $discount['average'], $csv);
        $this->assertStringContainsString((string) $discount['orders'], $csv);

        $this->assertSame(ExportType::Analytics, ExportLog::query()->first()->type);
        $this->assertGreaterThan(0, ExportLog::query()->first()->row_count);
    }

    public function test_a_content_editor_and_farmer_seller_cannot_export(): void
    {
        $farm = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $farmer = $this->farmer([], $farm);

        $this->assertFalse(OrderResource::canAccess());
        $this->actingAs($editor);
        $this->assertFalse(OrderResource::canAccess());
        $this->assertFalse(ExportLogResource::canAccess());

        $this->actingAs($farmer);
        $this->assertFalse(OrderResource::canAccess());
        $this->assertFalse(ExportLogResource::canAccess());

        try {
            app(ExportOrderLedgerAction::class)->download($editor, Order::query());
            $this->fail('Content Editor was allowed to export the ledger.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            app(ExportAnalyticsAction::class)->download($farmer, now()->startOfDay()->subDays(29));
            $this->fail('Farmer-seller was allowed to export analytics.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, ExportLog::query()->count());
    }

    public function test_super_admin_can_open_export_pages_and_a_content_editor_cannot(): void
    {
        $admin = $this->staff(Role::SuperAdmin);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/export-logs')
            ->assertOk();

        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());

        $this->actingAs($editor)
            ->get('/admin/orders')
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/export-logs')
            ->assertForbidden();
    }

    public function test_content_editor_cannot_export_another_farms_orders_through_the_action(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $sellerB = $this->farmer([], $farmB);
        $listingB = $this->listingFor($sellerB, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $this->completeOrder($sellerB, $this->placeOrder($this->buyer(), $listingB, 1), 30);

        try {
            app(ExportOrderLedgerAction::class)->download($editorA, Order::query()->where('farm_id', $farmB->id));
            $this->fail('Content Editor exported another farm.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, ExportLog::query()->count());
    }

    private ?string $lastDisposition = null;

    private function csvBody(StreamedResponse $response): string
    {
        $this->lastDisposition = (string) $response->headers->get('Content-Disposition');

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    private function sectionSum(string $csv, string $heading, int $column): float
    {
        $lines = preg_split('/\R/', $csv) ?: [];
        $total = 0.0;
        $inSection = false;
        $seenHeader = false;

        foreach ($lines as $line) {
            $line = ltrim($line, "\xEF\xBB\xBF");
            $cells = str_getcsv($line);

            if (($cells[0] ?? '') === $heading && count($cells) === 1) {
                $inSection = true;
                $seenHeader = false;

                continue;
            }

            if ($inSection && ($line === '' || $cells === [''])) {
                break;
            }

            if (! $inSection) {
                continue;
            }

            if (! $seenHeader) {
                $seenHeader = true;

                continue;
            }

            $total += (float) ($cells[$column] ?? 0);
        }

        return $total;
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
