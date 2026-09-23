<?php

namespace Tests\Feature\Api;

use App\Enums\ListingStatus;
use App\Enums\NotificationType;
use App\Support\InAppNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_placing_and_advancing_an_order_creates_in_app_notifications(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10, 'price_per_unit' => 30]);
        $buyer = $this->buyer();

        $order = $this->placeOrder($buyer, $listing, 1);

        $this->asUser($farmer)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->asUser($farmer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', NotificationType::OrderPlaced->value)
            ->assertJsonPath('data.0.related_id', $order->id);

        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/confirm")
            ->assertOk();

        $this->asUser($buyer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', NotificationType::OrderConfirmed->value)
            ->assertJsonPath('data.0.related_id', $order->id);
    }

    public function test_unauthenticated_notification_requests_return_401(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_user_can_mark_one_and_all_notifications_as_read(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 10]);
        $buyer = $this->buyer();

        $this->placeOrder($buyer, $listing, 1);

        $notificationId = $farmer->inAppNotifications()->value('id');

        $this->asUser($farmer)
            ->patchJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

        $this->asUser($farmer)
            ->getJson('/api/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);

        $second = $this->listingFor($farmer, ['quantity_available' => 10]);
        $this->placeOrder($buyer, $second, 1);

        $this->asUser($farmer)
            ->postJson('/api/notifications/read-all')
            ->assertOk();

        $this->asUser($farmer)
            ->getJson('/api/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_user_cannot_mark_someone_elses_notification(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(['email' => 'other@example.com']);
        $listing = $this->listingFor($farmer);
        $buyer = $this->buyer();

        $this->placeOrder($buyer, $listing, 1);

        $notificationId = $farmer->inAppNotifications()->value('id');

        $this->asUser($other)
            ->patchJson("/api/notifications/{$notificationId}/read")
            ->assertForbidden();
    }

    public function test_listing_takedown_and_restore_notify_the_seller(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Morning crate']);
        $notifier = app(InAppNotifier::class);

        $listing->forceFill([
            'status' => ListingStatus::TakenDown,
            'taken_down_at' => now(),
            'takedown_reason' => 'Priced below the floor.',
        ])->save();

        $notifier->listingTakenDown($farmer, $listing->fresh());

        $this->asUser($farmer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', NotificationType::ListingTakenDown->value)
            ->assertJsonPath('data.0.related_id', $listing->id)
            ->assertJsonPath('data.0.related_type', 'listing')
            ->assertJsonPath('data.0.title', 'Listing taken down')
            ->assertJsonPath(
                'data.0.body',
                'Morning crate was taken down by an administrator. Reason: Priced below the floor.',
            );

        $listing->forceFill([
            'status' => ListingStatus::Published,
            'taken_down_at' => null,
            'taken_down_by' => null,
            'takedown_reason' => null,
        ])->save();

        $notifier->listingRestored($farmer, $listing->fresh());

        $this->asUser($farmer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', NotificationType::ListingRestored->value)
            ->assertJsonPath('data.0.related_id', $listing->id)
            ->assertJsonPath('data.0.related_type', 'listing')
            ->assertJsonPath('data.0.title', 'Listing restored')
            ->assertJsonPath('data.0.body', 'Morning crate was restored by an administrator.');
    }
}
