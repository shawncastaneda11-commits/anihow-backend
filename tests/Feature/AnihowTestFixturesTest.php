<?php

namespace Tests\Feature;

use App\Enums\ListingUnit;
use App\Enums\OrderStatus;
use App\Enums\ProductCategory;
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
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $this->assertSame(11, User::query()->count());
        $this->assertTrue(Hash::check('password', (string) User::query()->where('email', 'kuyajun@gmail.com')->value('password')));
        $this->assertTrue(Hash::check('password', (string) User::query()->where('email', 'buyer01@gmail.com')->value('password')));
        $this->assertTrue(Hash::check('password', (string) User::query()->where('email', 'editor01@gmail.com')->value('password')));
        $this->assertFalse(Hash::check('password', (string) User::query()->where('email', 'admin01@gmail.com')->value('password')));
        $this->assertNull(User::query()->where('email', 'susp03@gmail.com')->value('farm_id'));
        $this->assertSame(0, User::query()->where('must_change_password', true)->count());
        $this->assertSame(0, User::query()->whereNotNull('temporary_password_expires_at')->count());
        $this->assertSame(4, Farm::query()->count());
        $staClara = Farm::query()->where('slug', 'sta-clara-farm')->first();
        $this->assertSame('Sta. Clara Farm', $staClara?->name);
        $this->assertTrue($staClara?->is_active);
        $this->assertNotNull($staClara?->address);
        $this->assertNull($staClara?->latitude);
        $this->assertNull($staClara?->contentEditor);
        $this->assertSame(0, User::query()->where('farm_id', $staClara?->id)->count());
        $this->assertDatabaseMissing('farms', ['slug' => 'mang-tonyo-farm']);
        $this->assertDatabaseMissing('farms', ['slug' => 'anihow-test-farm']);
        $truofa = Farm::query()->where('slug', 'truofa')->first();
        $sanctuario = Farm::query()->where('slug', 'sanctuario-nature-farm')->first();
        $this->assertSame('Tanza', $truofa?->municipality);
        $this->assertSame('Tanza Rural and Urban Organic Farmers Association.', $truofa?->description);
        $this->assertNull($truofa?->contentEditor);
        $this->assertNull($sanctuario?->municipality);
        $this->assertNull($sanctuario?->barangay);
        $this->assertNull($sanctuario?->contentEditor);
        $this->assertSame(9, Listing::query()->count());
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

        $mango = Listing::query()->where('title', 'Tuyong mangga')->first();
        $this->assertSame('alingnena@gmail.com', $mango?->farmerSeller?->email);
        $this->assertSame(ProductCategory::ValueAdded, $mango?->cropType?->category);
        $this->assertSame(ListingUnit::Pack, $mango?->unit);
        $this->assertNull($mango?->cropType?->farm_id);
        $this->assertTrue($mango?->harvestRecords()->exists());

        $delete = User::query()->where('email', 'delete01@gmail.com')->first();
        $this->assertSame(0, $delete?->orders()->count());
        $this->assertTrue($delete?->accountDeletionRequests()->exists());

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('exists')
            ->doesntExpectOutputToContain('created')
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $this->assertSame(11, User::query()->count());
        $this->assertSame(9, Listing::query()->count());
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

    public function test_price_guards_are_brought_to_the_values_the_test_cases_expect(): void
    {
        CropType::query()->where('name', 'Pechay')->update(['floor_price' => 30.00]);
        CropType::query()->where('name', 'Kamatis')->update(['max_discount' => 5.00]);
        CropType::query()->where('name', 'Talong')->update(['max_discount' => 2.00]);

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('updated floor price to ₱12.00')
            ->assertSuccessful();

        $this->assertSame(12.0, (float) CropType::query()->where('name', 'Pechay')->value('floor_price'));
        $this->assertSame(15.0, (float) CropType::query()->where('name', 'Kamatis')->value('max_discount'));
        $this->assertSame(5.0, (float) CropType::query()->where('name', 'Talong')->value('max_discount'));
        $this->assertSame(10.0, (float) CropType::query()->where('name', 'Okra')->value('max_discount'));
        $this->assertTrue(Listing::query()->where('title', 'Pechay, sariwa')->exists());
    }

    public function test_a_maximum_tawad_that_would_reach_the_floor_is_left_alone(): void
    {
        CropType::query()->where('name', 'Kamatis')->update(['floor_price' => 15.00, 'max_discount' => 5.00]);

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('the maximum tawad must stay below the floor price')
            ->assertSuccessful();

        $this->assertSame(5.0, (float) CropType::query()->where('name', 'Kamatis')->value('max_discount'));
    }

    public function test_production_makes_no_test_farm_and_changes_no_price_guards(): void
    {
        $this->app['env'] = 'production';
        CropType::query()->where('name', 'Pechay')->update(['floor_price' => 30.00]);

        $this->artisan('anihow:test-fixtures', ['--force' => true])
            ->expectsOutputToContain('test farm is not made in production')
            ->assertSuccessful();

        $this->assertDatabaseMissing('farms', ['slug' => 'sta-clara-farm']);
        $this->assertSame(30.0, (float) CropType::query()->where('name', 'Pechay')->value('floor_price'));
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
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Farm::query()->count());
        $this->assertSame(0, Listing::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_dry_run_reports_price_guard_changes_without_making_them(): void
    {
        CropType::query()->where('name', 'Pechay')->update(['floor_price' => 30.00]);

        $this->artisan('anihow:test-fixtures', ['--dry-run' => true])
            ->expectsOutputToContain('would update floor price to ₱12.00')
            ->assertSuccessful();

        $this->assertSame(30.0, (float) CropType::query()->where('name', 'Pechay')->value('floor_price'));
    }

    public function test_the_value_added_crop_joins_a_farm_that_has_its_own_crop_types(): void
    {
        $pyap = Farm::factory()->create(['name' => 'PYAP Manggahan Chapter', 'slug' => 'pyap-manggahan-chapter']);
        CropType::query()->whereNull('farm_id')->get()->each(
            fn (CropType $crop) => $crop->replicate()->forceFill(['farm_id' => $pyap->id])->save(),
        );

        $this->artisan('anihow:test-fixtures')->assertSuccessful();

        $mango = CropType::query()->where('name', 'Tuyong mangga')->sole();
        $this->assertSame($pyap->id, $mango->farm_id);
        $this->assertSame($mango->id, Listing::query()->where('title', 'Tuyong mangga')->value('crop_type_id'));
    }

    public function test_the_value_added_listing_is_skipped_when_the_farm_turns_value_added_off(): void
    {
        $this->artisan('anihow:test-fixtures')->assertSuccessful();
        Listing::query()->where('title', 'Tuyong mangga')->forceDelete();
        Farm::query()->where('slug', 'pyap-manggahan-chapter')->update(['value_added_enabled' => false]);

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('value-added products are turned off')
            ->assertSuccessful();

        $this->assertFalse(Listing::query()->where('title', 'Tuyong mangga')->exists());
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
