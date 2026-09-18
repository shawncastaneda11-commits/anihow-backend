<?php

namespace Tests\Feature\Api;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Mail\ReservationCreatedMail;
use App\Mail\ReservationStatusChangedMail;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Mail::fake();
    }

    public function test_reservation_and_status_changes_create_in_app_notifications(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create(['quantity_available' => 10]);
        $buyer = $this->buyer();

        $id = $this->asUser($buyer)
            ->postJson('/api/buyer/reservations', [
                'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $this->asUser($farmer)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->asUser($farmer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', NotificationType::ReservationCreated->value);

        Mail::assertQueued(ReservationCreatedMail::class);

        $this->asUser($buyer)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/reservations/{$id}/ready")
            ->assertOk();

        Mail::assertQueued(ReservationStatusChangedMail::class);

        $this->asUser($buyer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', NotificationType::ReservationStatusChanged->value)
            ->assertJsonCount(1, 'data');
    }

    public function test_low_stock_notifies_farmer_once_when_crossing_threshold(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create([
            'name' => 'Tomato',
            'quantity_available' => 5,
        ]);
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
        ])->assertCreated();

        $types = $farmer->inAppNotifications()->get()->map(
            fn ($notification) => $notification->type->value,
        )->all();

        $this->assertContains(NotificationType::ListingLowStock->value, $types);
        $this->assertContains(NotificationType::ReservationCreated->value, $types);
    }

    public function test_user_can_mark_one_and_all_notifications_as_read(): void
    {
        $farmer = $this->farmer();
        $listing = Listing::factory()->forFarmer($farmer)->create(['quantity_available' => 10]);
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
        ]);

        $notificationId = $farmer->inAppNotifications()->value('id');

        $this->asUser($farmer)
            ->patchJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

        $this->asUser($farmer)
            ->getJson('/api/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_user_cannot_mark_someone_elses_notification(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(['email' => 'other@example.com']);
        $listing = Listing::factory()->forFarmer($farmer)->create();
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'items' => [['listing_id' => $listing->id, 'quantity' => 1]],
        ]);

        $notificationId = $farmer->inAppNotifications()->value('id');

        $this->asUser($other)
            ->patchJson("/api/notifications/{$notificationId}/read")
            ->assertForbidden();
    }

    private function farmer(array $attributes = []): User
    {
        $farmer = User::factory()->create($attributes);
        $farmer->assignRole(Role::FarmerSeller);

        return $farmer;
    }

    private function buyer(array $attributes = []): User
    {
        $buyer = User::factory()->create($attributes);
        $buyer->assignRole(Role::Buyer);

        return $buyer;
    }

    private function asUser(User $user): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('mobile')->plainTextToken);
    }
}
