<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\StallMessage;
use App\Models\User;
use App\Support\Demo\ClientFarms;
use App\Support\ListingStorage;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_idempotent(): void
    {
        $this->artisan('anihow:demo-data', ['--weeks' => 4])
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $first = $this->snapshot();

        $this->artisan('anihow:demo-data', ['--weeks' => 4])->assertSuccessful();

        $this->assertSame($first, $this->snapshot());
    }

    public function test_existing_farm_keeps_what_the_admin_typed(): void
    {
        $farm = Farm::query()->create([
            'name' => 'PYAP Manggahan Chapter',
            'slug' => ClientFarms::PYAP_SLUG,
            'contact_person' => 'Admin Typed',
            'contact_number' => '09181234567',
            'latitude' => 14.3123456,
            'longitude' => 120.8123456,
            'description' => 'Typed by the admin.',
            'address' => 'Admin address',
            'is_active' => true,
        ]);

        $this->artisan('anihow:demo-data', ['--weeks' => 1])->assertSuccessful();

        $farm->refresh();

        $this->assertSame('PYAP Manggahan Chapter', $farm->name);
        $this->assertSame(ClientFarms::PYAP_SLUG, $farm->slug);
        $this->assertSame('Admin Typed', $farm->contact_person);
        $this->assertSame('09181234567', $farm->contact_number);
        $this->assertEquals(14.3123456, (float) $farm->latitude);
        $this->assertEquals(120.8123456, (float) $farm->longitude);
        $this->assertSame('Typed by the admin.', $farm->description);
        $this->assertSame('Admin address', $farm->address);
        $this->assertNotSame('Ka Elena Ramos', $farm->contact_person);
        $this->assertNotSame('09175552100', $farm->contact_number);
    }

    public function test_the_old_demo_contact_on_pyap_is_cleared(): void
    {
        $farm = Farm::query()->create([
            'name' => 'PYAP Manggahan Chapter',
            'slug' => ClientFarms::PYAP_SLUG,
            'contact_person' => 'Ka Elena Ramos',
            'contact_number' => '09175552100',
            'is_active' => true,
        ]);

        $this->artisan('anihow:demo-data', ['--weeks' => 1])->assertSuccessful();

        $farm->refresh();

        $this->assertNull($farm->contact_person);
        $this->assertNull($farm->contact_number);
    }

    public function test_fixture_orders_get_no_demo_reviews_or_chats(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->artisan('anihow:test-fixtures')->assertSuccessful();

        $fixtureOrderIds = Order::query()->pluck('id');
        $fixtureReviews = Review::query()->whereIn('order_id', $fixtureOrderIds)->count();

        $this->artisan('anihow:demo-data', ['--weeks' => 4])->assertSuccessful();

        $this->assertSame($fixtureReviews, Review::query()->whereIn('order_id', $fixtureOrderIds)->count());
        $this->assertFalse(StallMessage::query()->whereIn('order_id', $fixtureOrderIds)->exists());
        $this->assertSame(1, Farm::query()->where('slug', ClientFarms::PYAP_SLUG)->first()?->contentEditor()->count());
    }

    public function test_scheduler_commands_do_not_change_open_records(): void
    {
        $this->artisan('anihow:demo-data', ['--weeks' => 4])->assertSuccessful();

        $before = $this->statuses();

        Artisan::call('orders:sweep-stale');
        Artisan::call('payments:upkeep');
        Artisan::call('reservations:open-due');
        Artisan::call('listings:harvest-upkeep');
        Artisan::call('payments:purge-proof-files');
        Artisan::call('announcements:notify-due');

        $this->assertSame($before, $this->statuses());
    }

    public function test_demo_listing_photos_are_real_produce_images(): void
    {
        $this->artisan('anihow:demo-data', ['--weeks' => 1])->assertSuccessful();

        $listing = Listing::query()->where('title', 'like', 'Talong, pantatong')->first();

        $this->assertNotNull($listing?->image_path);
        $this->assertTrue(ListingStorage::disk()->exists($listing->image_path));

        $info = getimagesizefromstring((string) ListingStorage::disk()->get($listing->image_path));

        $this->assertIsArray($info);
        $this->assertNotSame([800, 500], [$info[0], $info[1]]);
    }

    public function test_demo_seeder_sends_and_queues_no_mail_to_demo_addresses(): void
    {
        Mail::fake();

        $this->artisan('anihow:demo-data', ['--weeks' => 1])->assertSuccessful();

        $demoEmails = User::query()
            ->where('email', 'like', '%'.DemoSeeder::EMAIL_DOMAIN)
            ->pluck('email');

        $this->assertNotEmpty($demoEmails);

        foreach ($demoEmails as $email) {
            Mail::assertNotOutgoing(Mailable::class, $email);
        }

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    /**
     * @return array{orders: int, users: int, listings: int, harvests: int, reviews: int}
     */
    private function snapshot(): array
    {
        return [
            'orders' => Order::query()->count(),
            'users' => User::query()->count(),
            'listings' => Listing::query()->count(),
            'harvests' => HarvestRecord::query()->count(),
            'reviews' => Review::query()->count(),
        ];
    }

    /**
     * @return array{orders: list<array<string, mixed>>, reservations: list<array<string, mixed>>, proofs: list<array<string, mixed>>}
     */
    private function statuses(): array
    {
        return [
            'orders' => Order::query()->orderBy('id')->get(['id', 'status', 'payment_status'])->map(fn (Order $order): array => [
                'id' => $order->id,
                'status' => $order->status?->value,
                'payment_status' => $order->payment_status?->value,
            ])->all(),
            'reservations' => Reservation::query()->orderBy('id')->get(['id', 'status', 'payment_status'])->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'status' => $reservation->status?->value,
                'payment_status' => $reservation->payment_status?->value,
            ])->all(),
            'proofs' => PaymentProof::query()->orderBy('id')->get(['id', 'status'])->map(fn (PaymentProof $proof): array => [
                'id' => $proof->id,
                'status' => $proof->status?->value,
            ])->all(),
        ];
    }
}
