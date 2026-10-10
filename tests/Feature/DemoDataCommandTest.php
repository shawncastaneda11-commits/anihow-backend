<?php

namespace Tests\Feature;

use App\Enums\ListingUnit;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentProofStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\StockRemoval;
use App\Models\User;
use App\Services\FarmerSalesAnalytics;
use App\Services\HarvestAnalytics;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Demo\ClientFarms;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_six_months_of_history_for_the_three_client_farms(): void
    {
        $this->artisan('anihow:demo-data', ['--weeks' => 26])
            ->expectsOutputToContain('Farms')
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $pyap = Farm::query()->where('slug', ClientFarms::PYAP_SLUG)->first();
        $truofa = Farm::query()->where('slug', ClientFarms::TRUOFA_SLUG)->first();
        $sanctuario = Farm::query()->where('slug', ClientFarms::SANCTUARIO_SLUG)->first();

        $this->assertNotNull($pyap);
        $this->assertNotNull($truofa);
        $this->assertNotNull($sanctuario);
        $this->assertSame('editor01@gmail.com', $pyap->contentEditor?->email);
        $this->assertSame('editor.sanctuario@demo.anihow.local', $sanctuario->contentEditor?->email);
        $this->assertNull($truofa->contentEditor);
        $this->assertDatabaseMissing('users', ['email' => 'elena.ramos@demo.anihow.local']);

        $demoUsers = User::query()->where(function ($query): void {
            $query->where('email', 'like', '%@demo.anihow.local')
                ->orWhere('email', 'editor01@gmail.com');
        })->get();

        $this->assertNotEmpty($demoUsers);

        foreach ($demoUsers as $user) {
            $this->assertTrue(Hash::check('password', (string) $user->password), $user->email);
            $this->assertNull($user->phone);
            $this->assertNull($user->contact);
            $this->assertFalse($user->must_change_password);
            $this->assertNull($user->temporary_password_expires_at);
        }

        $shops = User::query()->whereNotNull('shop_name')->pluck('shop_name');
        $this->assertSame($shops->count(), $shops->unique()->count());
        $this->assertFalse($shops->contains('Kuya Jun Harvest'));
        $this->assertFalse($shops->contains('Aling Nena Produce'));
        $this->assertFalse($shops->contains('Mang Tonyo Farm'));
        $this->assertFalse($shops->contains('PYAP Manggahan Chapter'));
        $this->assertFalse($shops->contains('TRUOFA'));
        $this->assertFalse($shops->contains('Sanctuario Nature Farm'));

        $earliest = Order::query()->min('created_at');
        $this->assertNotNull($earliest);
        $this->assertTrue(Carbon::parse((string) $earliest)->lessThanOrEqualTo(now()->subDays(175)));

        foreach ([$pyap, $truofa, $sanctuario] as $farm) {
            $this->assertTrue(HarvestRecord::query()->where('farm_id', $farm->id)->exists(), $farm->name);
            $this->assertTrue(StockRemoval::query()->where('farm_id', $farm->id)->exists(), $farm->name);
            $this->assertTrue(
                Order::query()->where('farm_id', $farm->id)->where('source', OrderSource::WalkIn)->exists(),
                $farm->name,
            );
            $this->assertTrue(
                Order::query()->where('farm_id', $farm->id)->where('status', OrderStatus::Completed)->exists(),
                $farm->name,
            );
            $this->assertTrue(
                Order::query()->where('farm_id', $farm->id)->where('status', OrderStatus::Cancelled)->exists(),
                $farm->name,
            );
        }

        $this->assertTrue(PaymentProof::query()->where('status', PaymentProofStatus::Accepted)->exists());
        $this->assertTrue(PaymentProof::query()->where('status', PaymentProofStatus::Rejected)->exists());
        $this->assertTrue(Order::query()->where('payment_status', OrderPaymentStatus::Refunded)->exists());
        $this->assertTrue(Reservation::query()->where('status', ReservationStatus::Converted)->exists());
        $this->assertTrue(
            Reservation::query()
                ->where('status', ReservationStatus::Active)
                ->where('payment_status', OrderPaymentStatus::Paid)
                ->exists(),
        );
        $this->assertSame(0, Listing::query()->where('quantity_available', '<=', 0)->count());

        $seller = User::query()->where('email', 'liza.cruz@demo.anihow.local')->first();
        $this->assertNotNull($seller);
        $range = AnalyticsRange::fromValues('year', null, null, null, 'all');
        $sales = app(FarmerSalesAnalytics::class)->build($seller, $range);
        $harvest = app(HarvestAnalytics::class)->build($seller, $range);

        $this->assertGreaterThan(0, $sales['totals']['orders']);
        $this->assertGreaterThan(0, $harvest['records']);

        foreach ([$pyap, $truofa, $sanctuario] as $farm) {
            $harvested = (float) HarvestRecord::query()->where('farm_id', $farm->id)->sum('quantity_good');
            $sold = (float) OrderItem::query()
                ->whereHas('order', fn ($query) => $query
                    ->where('farm_id', $farm->id)
                    ->where('status', OrderStatus::Completed))
                ->sum('quantity');
            $this->assertGreaterThan(0.6, $sold / $harvested, $farm->name.' harvests should track sales');

            $weekdays = Order::query()
                ->where('farm_id', $farm->id)
                ->where('created_at', '>=', now()->subWeeks(8))
                ->pluck('created_at')
                ->map(fn ($createdAt): int => Carbon::parse($createdAt)->dayOfWeek)
                ->unique();
            $this->assertGreaterThanOrEqual(4, $weekdays->count(), $farm->name.' orders should spread over the week');
        }

        $this->assertGreaterThan(1, OrderItem::query()->distinct()->count('quantity'));
    }

    public function test_production_refuses_to_run_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('anihow:demo-data')
            ->expectsOutputToContain('Refusing to run in production without --force.')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_reset_then_demo_then_fixtures_keeps_one_pyap_editor(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seedCropTypes();

        $this->artisan('anihow:test-fixtures')->assertSuccessful();
        $this->artisan('anihow:reset-accounts', ['--execute' => true])->assertSuccessful();
        $this->artisan('anihow:demo-data', ['--weeks' => 4])->assertSuccessful();
        $this->artisan('anihow:test-fixtures')->assertSuccessful();

        $titles = Listing::query()->pluck('title');
        $this->assertSame($titles->count(), $titles->unique()->count(), 'Demo and fixture listings must not share a name');

        $pyap = Farm::query()->where('slug', ClientFarms::PYAP_SLUG)->first();
        $truofa = Farm::query()->where('slug', ClientFarms::TRUOFA_SLUG)->first();

        $this->assertNotNull($pyap);
        $this->assertNotNull($truofa);
        $this->assertSame(
            1,
            User::query()->role(Role::ContentEditor->value)->where('farm_id', $pyap->id)->count(),
        );
        $this->assertSame('editor01@gmail.com', $pyap->contentEditor?->email);
        $this->assertSame(
            0,
            User::query()->role(Role::ContentEditor->value)->where('farm_id', $truofa->id)->count(),
        );
    }

    private function seedCropTypes(): void
    {
        $rows = [
            ['Pechay', 'Pechay', 'Pechay', ListingUnit::Bundle, 12.00, 6.00],
            ['Kalabasa', 'Squash', 'Kalabasa', ListingUnit::Kilogram, 20.00, 10.00],
            ['Talong', 'Eggplant', 'Talong', ListingUnit::Kilogram, 25.00, 12.00],
            ['Kamatis', 'Tomato', 'Kamatis', ListingUnit::Kilogram, 35.00, 15.00],
            ['Okra', 'Okra', 'Okra', ListingUnit::Kilogram, 25.00, 10.00],
        ];

        foreach ($rows as [$name, $en, $fil, $unit, $floor, $max]) {
            CropType::query()->create([
                'name' => $name,
                'slug' => 'fixture-'.str($name)->slug(),
                'label_en' => $en,
                'label_fil' => $fil,
                'unit_of_measure' => $unit,
                'floor_price' => $floor,
                'max_discount' => $max,
                'is_active' => true,
            ]);
        }
    }
}
