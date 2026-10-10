<?php

namespace Tests\Feature;

use App\Enums\ListingUnit;
use App\Enums\PaymentProofStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Report;
use App\Models\SellerPaymentQr;
use App\Models\StallConversation;
use App\Models\StallMessage;
use App\Models\User;
use App\Support\ListingStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seedCropTypes();
    }

    public function test_dry_run_writes_nothing_and_lists_counts(): void
    {
        $admin = User::factory()->create(['email' => 'admin01@gmail.com']);
        $admin->syncRoles(Role::SuperAdmin);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $buyer->syncRoles(Role::Buyer);
        Farm::factory()->create(['name' => 'Gone Farm', 'slug' => 'gone-farm']);
        $kept = Farm::factory()->create([
            'name' => 'PYAP Manggahan Chapter',
            'slug' => 'pyap-manggahan-chapter',
        ]);

        $this->artisan('anihow:reset-accounts')
            ->expectsOutputToContain('would delete')
            ->expectsOutputToContain('admin01@gmail.com')
            ->expectsOutputToContain('Gone Farm')
            ->expectsOutputToContain('PYAP Manggahan Chapter')
            ->assertSuccessful();

        $this->assertModelExists($buyer);
        $this->assertModelExists($kept);
        $this->assertDatabaseHas('farms', ['slug' => 'gone-farm']);
        $this->assertSame(2, User::query()->count());
    }

    public function test_execute_keeps_super_admins_client_farms_and_global_crops(): void
    {
        Storage::fake('local');
        Storage::fake(ListingStorage::diskName());

        $this->artisan('anihow:test-fixtures')->assertSuccessful();

        $pyap = Farm::query()->where('slug', 'pyap-manggahan-chapter')->firstOrFail();
        Storage::disk(ListingStorage::diskName())->put('farms/pyap-cover.jpg', 'keep');
        $pyap->update(['cover_photo_path' => 'farms/pyap-cover.jpg']);

        $gone = Farm::factory()->create([
            'name' => 'Gone Farm',
            'slug' => 'gone-farm',
            'cover_photo_path' => 'farms/gone-cover.jpg',
        ]);
        Storage::disk(ListingStorage::diskName())->put('farms/gone-cover.jpg', 'gone');

        $seller = User::query()->where('email', 'kuyajun@gmail.com')->firstOrFail();
        $crop = CropType::factory()->create([
            'farm_id' => $gone->id,
            'created_by' => $seller->id,
        ]);
        HarvestRecord::factory()->create([
            'listing_id' => null,
            'farmer_seller_id' => $seller->id,
            'farm_id' => $gone->id,
            'crop_type_id' => $crop->id,
            'recorded_by' => $seller->id,
        ]);

        $ghost = User::factory()->create(['email' => 'ghost@example.com']);
        $ghost->syncRoles(Role::Buyer);
        $ghost->delete();

        $order = Order::query()->firstOrFail();
        $proofPath = 'payment-proofs/'.$order->id.'/shot.jpg';
        Storage::disk('local')->put($proofPath, 'proof');
        PaymentProof::query()->create([
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'farmer_seller_id' => $order->farmer_seller_id,
            'reference_number' => 'TESTPROOF1',
            'amount' => 20,
            'screenshot_path' => $proofPath,
            'status' => PaymentProofStatus::Pending,
        ]);

        $qrPath = (string) SellerPaymentQr::query()->value('image_path');
        $this->assertNotSame('', $qrPath);
        $this->assertTrue(Storage::disk('local')->exists($qrPath));

        $buyer = User::query()->where('email', 'buyer01@gmail.com')->firstOrFail();
        $conversation = StallConversation::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $seller->id,
        ]);
        $attachment = 'chat/'.$conversation->id.'/note.jpg';
        Storage::disk('local')->put($attachment, 'chat');
        StallMessage::query()->create([
            'stall_conversation_id' => $conversation->id,
            'user_id' => $buyer->id,
            'body' => 'See you at the farm.',
            'attachment_path' => $attachment,
        ]);

        $admin = User::query()->where('email', 'admin01@gmail.com')->firstOrFail();
        Report::query()->create([
            'reporter_id' => $admin->id,
            'reportable_type' => Listing::class,
            'reportable_id' => Listing::query()->value('id'),
            'reason' => ReportReason::Other,
            'status' => ReportStatus::Open,
        ]);

        $globalCrops = CropType::query()->whereNull('farm_id')->count();

        $this->artisan('anihow:reset-accounts', ['--execute' => true])
            ->expectsOutputToContain('deleted')
            ->expectsOutputToContain('admin01@gmail.com')
            ->assertSuccessful();

        $this->assertSame(['admin01@gmail.com'], User::withTrashed()->pluck('email')->all());
        $this->assertEqualsCanonicalizing(
            ['pyap-manggahan-chapter', 'truofa', 'sanctuario-nature-farm'],
            Farm::query()->pluck('slug')->all(),
        );
        $this->assertSame($globalCrops, CropType::query()->count());
        $this->assertSame(0, CropType::query()->whereNotNull('farm_id')->count());
        $this->assertSame(0, Listing::withTrashed()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Report::query()->count());
        $this->assertSame(0, HarvestRecord::query()->count());
        $this->assertSame(0, PaymentProof::query()->count());
        $this->assertSame(0, SellerPaymentQr::withTrashed()->count());
        $this->assertSame(0, StallMessage::query()->count());
        $this->assertFalse(User::withTrashed()->where('email', 'ghost@example.com')->exists());
        $this->assertFalse(Storage::disk('local')->exists($proofPath));
        $this->assertFalse(Storage::disk('local')->exists($qrPath));
        $this->assertFalse(Storage::disk('local')->exists($attachment));
        $this->assertFalse(Storage::disk(ListingStorage::diskName())->exists('farms/gone-cover.jpg'));
        $this->assertTrue(Storage::disk(ListingStorage::diskName())->exists('farms/pyap-cover.jpg'));
        $pyap->refresh();
        $this->assertSame('farms/pyap-cover.jpg', $pyap->cover_photo_path);
    }

    public function test_it_refuses_when_no_active_super_admin_would_remain(): void
    {
        $admin = User::factory()->create([
            'email' => 'asleep@example.com',
            'status' => UserStatus::Suspended,
        ]);
        $admin->syncRoles(Role::SuperAdmin);
        $buyer = User::factory()->create(['email' => 'buyer@example.com']);
        $buyer->syncRoles(Role::Buyer);
        Farm::factory()->create(['slug' => 'gone-farm']);

        $this->artisan('anihow:reset-accounts')
            ->expectsOutputToContain('no active Super Admin would remain')
            ->assertFailed();

        $this->artisan('anihow:reset-accounts', ['--execute' => true])
            ->expectsOutputToContain('no active Super Admin would remain')
            ->assertFailed();

        $this->assertModelExists($admin);
        $this->assertModelExists($buyer);
        $this->assertDatabaseHas('farms', ['slug' => 'gone-farm']);
    }

    public function test_production_refuses_without_force(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);
        $this->app['env'] = 'production';

        $this->artisan('anihow:reset-accounts', ['--execute' => true])
            ->expectsOutputToContain('Refusing to run in production without --force.')
            ->assertFailed();

        $this->assertModelExists($admin);
    }

    public function test_a_second_execute_deletes_nothing(): void
    {
        Storage::fake('local');
        Storage::fake(ListingStorage::diskName());
        $this->artisan('anihow:test-fixtures')->assertSuccessful();

        $this->artisan('anihow:reset-accounts', ['--execute' => true])->assertSuccessful();

        $users = User::withTrashed()->count();
        $farms = Farm::query()->count();

        $this->artisan('anihow:reset-accounts', ['--execute' => true])
            ->expectsOutputToContain('deleted')
            ->assertSuccessful();

        $this->assertSame($users, User::withTrashed()->count());
        $this->assertSame($farms, Farm::query()->count());
        $this->assertSame(0, Listing::withTrashed()->count());
    }

    public function test_fixtures_can_be_recreated_after_a_reset(): void
    {
        Storage::fake('local');
        Storage::fake(ListingStorage::diskName());
        $this->artisan('anihow:test-fixtures')->assertSuccessful();
        $this->artisan('anihow:reset-accounts', ['--execute' => true])->assertSuccessful();

        $this->artisan('anihow:test-fixtures')
            ->expectsOutputToContain('created')
            ->assertSuccessful();

        $this->assertSame(11, User::query()->count());
        $this->assertSame(3, Farm::query()->count());
        $this->assertSame(8, Listing::query()->count());
        $this->assertNull(User::query()->where('email', 'susp03@gmail.com')->value('farm_id'));
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
