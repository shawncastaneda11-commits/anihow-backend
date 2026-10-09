<?php

namespace Tests\Feature;

use App\Enums\ListingUnit;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AccountDeletionRequest;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TawadRule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnihowTestFixturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seedCropTypes();
    }

    public function test_running_the_command_twice_creates_each_record_once(): void
    {
        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('created')
            ->doesntExpectOutputToContain('Admin@1234')
            ->doesntExpectOutputToContain('Seller@1234')
            ->doesntExpectOutputToContain('Buyer@1234')
            ->doesntExpectOutputToContain('Editor@1234')
            ->assertSuccessful();

        $this->assertSame(11, User::query()->count());
        $this->assertSame(0, User::query()->where('must_change_password', true)->count());
        $this->assertSame(0, User::query()->whereNotNull('temporary_password_expires_at')->count());
        $this->assertSame(3, Farm::query()->count());
        $this->assertSame(8, Listing::query()->count());
        $this->assertSame(2, Reservation::query()->count());
        $opensInThreeDays = Listing::query()->where('title', 'Pechay (opens in 3 days)')->first();
        $this->assertNotNull($opensInThreeDays);
        $this->assertTrue($opensInThreeDays->available_from?->greaterThan(now()->addHour()));
        $this->assertSame('kuyajun@gmail.com', $opensInThreeDays->farmerSeller?->email);
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(1, Review::query()->count());
        $this->assertSame(2, Report::query()->count());
        $this->assertSame(1, AccountDeletionRequest::query()->count());
        $this->assertSame(1, FarmAnnouncement::query()->count());
        $this->assertSame(2, TawadRule::query()->count());

        $pechay = Order::query()->whereHas('items', fn ($query) => $query->where('listing_name', 'Pechay, sariwa'))->first();
        $okra = Order::query()->whereHas('items', fn ($query) => $query->where('listing_name', 'Okra Sariwa'))->first();
        $kalabasa = Order::query()->whereHas('items', fn ($query) => $query->where('listing_name', 'Kalabasa, pangkare-kare'))->first();

        $this->assertSame(OrderStatus::Completed, $pechay?->status);
        $this->assertNull($pechay?->review);
        $this->assertSame(OrderStatus::Completed, $okra?->status);
        $this->assertSame(1, $okra?->review?->rating);
        $this->assertSame('Ang bagal, walang kwentang seller!!', $okra?->review?->comment);
        $this->assertSame(OrderStatus::Placed, $kalabasa?->status);

        $delete = User::query()->where('email', 'delete01@gmail.com')->first();
        $this->assertSame(0, $delete?->orders()->count());
        $this->assertTrue($delete?->accountDeletionRequests()->exists());

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('exists')
            ->doesntExpectOutputToContain('created')
            ->assertSuccessful();

        $this->assertSame(11, User::query()->count());
        $this->assertSame(8, Listing::query()->count());
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(1, Review::query()->count());
    }

    public function test_an_existing_password_is_left_alone(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin01@gmail.com',
            'password' => 'Other@9999',
        ]);
        $hash = $admin->password;

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('exists')
            ->assertSuccessful();

        $admin->refresh();

        $this->assertSame($hash, $admin->password);
        $this->assertTrue(Hash::check('Other@9999', $admin->password));
    }

    public function test_a_farm_that_already_has_a_content_editor_does_not_get_another(): void
    {
        $farm = Farm::factory()->create([
            'name' => 'PYAP Manggahan Chapter',
            'slug' => 'pyap-manggahan-chapter',
        ]);
        $editor = User::factory()->create([
            'email' => 'elena.ramos@demo.anihow.local',
            'farm_id' => $farm->id,
            'status' => UserStatus::Active,
        ]);
        $editor->syncRoles(Role::ContentEditor);

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('elena.ramos@demo.anihow.local')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'editor01@gmail.com']);
        $this->assertSame(1, $farm->contentEditor()->count());
    }

    public function test_production_refuses_to_run_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('Refusing to run in production without --force.')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Farm::query()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('anihow:test-fixtures', ['--dry-run' => true])
            ->expectsOutputToContain('would create')
            ->doesntExpectOutputToContain('Admin@1234')
            ->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Farm::query()->count());
        $this->assertSame(0, Listing::query()->count());
        $this->assertSame(0, Order::query()->count());
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
