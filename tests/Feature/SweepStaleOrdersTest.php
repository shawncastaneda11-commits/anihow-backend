<?php

namespace Tests\Feature;

use App\Enums\CancellationReason;
use App\Enums\NotificationType;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Support\InAppNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class SweepStaleOrdersTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reminder_is_sent_once_after_twelve_hours_and_not_again(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $listing = $this->heldListing($farmer);
        $order = $this->placeOrder($this->buyer(), $listing, 2);

        $this->backdateCreatedAt($order, now()->subHours(12));

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $this->assertNotNull($order->fresh()->reminder_sent_at);
        $this->assertSame(OrderStatus::Placed, $order->fresh()->status);
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::OrderAwaitingConfirmation));
        $this->assertSame(2.0, (float) $listing->fresh()->quantity_held);

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $this->assertSame(1, $this->notices($farmer->id, NotificationType::OrderAwaitingConfirmation));
        $this->assertSame(OrderStatus::Placed, $order->fresh()->status);
    }

    public function test_auto_cancel_at_forty_eight_hours_releases_held_stock_exactly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $listing = $this->heldListing($farmer, available: 10);
        $order = $this->placeOrder($this->buyer(), $listing, 2);

        $this->assertSame(2.0, (float) $listing->fresh()->quantity_held);
        $this->assertSame(10.0, (float) $listing->fresh()->quantity_available);

        $this->backdateCreatedAt($order, now()->subHours(48));

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $order->refresh();
        $listing->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(CancellationReason::SellerUnresponsive, $order->cancellation_reason);
        $this->assertSame(OrderActor::System, $order->cancelled_by);
        $this->assertSame(0.0, (float) $listing->quantity_held);
        $this->assertSame(10.0, (float) $listing->quantity_available);
    }

    public function test_buyer_is_notified_and_history_records_the_system_cancellation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $listing = $this->heldListing($farmer);
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);

        $this->backdateCreatedAt($order, now()->subHours(48));

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $this->assertSame(1, $this->notices($buyer->id, NotificationType::OrderCancelled));
        $this->assertSame(0, $this->notices($farmer->id, NotificationType::OrderCancelled));

        $history = $order->statusHistories()->latest('id')->first();

        $this->assertNotNull($history);
        $this->assertSame(OrderStatus::Placed, $history->from_status);
        $this->assertSame(OrderStatus::Cancelled, $history->to_status);
        $this->assertNull($history->changed_by);
        $this->assertSame('Automatically cancelled after the seller did not respond.', $history->note);
    }

    public function test_confirmed_ready_and_walk_in_orders_are_untouched(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $confirmedListing = $this->heldListing($farmer, available: 8);
        $readyListing = $this->heldListing($farmer, available: 8);
        $walkInListing = $this->heldListing($farmer, available: 8);

        $confirmed = $this->placeOrder($this->buyer(), $confirmedListing, 1);
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$confirmed->id}/confirm")->assertOk();

        $ready = $this->placeOrder($this->buyer(), $readyListing, 1);
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$ready->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$ready->id}/ready")->assertOk();

        $walkIn = $this->asUser($farmer)
            ->postJson('/api/farmer/walk-in-sales', [
                'listing_id' => $walkInListing->id,
                'quantity' => 1,
                'amount_received' => 30,
            ])
            ->assertCreated();

        $walkInOrder = Order::query()->findOrFail($walkIn->json('data.id'));

        $this->backdateCreatedAt($confirmed, now()->subHours(48));
        $this->backdateCreatedAt($ready, now()->subHours(48));
        $this->backdateCreatedAt($walkInOrder, now()->subHours(48));

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $this->assertSame(OrderStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(OrderStatus::Ready, $ready->fresh()->status);
        $this->assertSame(OrderStatus::Completed, $walkInOrder->fresh()->status);
        $this->assertSame(0.0, (float) $confirmedListing->fresh()->quantity_held);
        $this->assertSame(0.0, (float) $readyListing->fresh()->quantity_held);
        $this->assertSame(0.0, (float) $walkInListing->fresh()->quantity_held);
    }

    public function test_cancelling_one_farm_order_does_not_release_another_farms_hold(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Manila'));

        $farmA = Farm::factory()->create(['name' => 'Farm A']);
        $farmB = Farm::factory()->create(['name' => 'Farm B']);
        $sellerA = $this->farmer([], $farmA);
        $sellerB = $this->farmer([], $farmB);
        $listingA = $this->heldListing($sellerA, available: 10);
        $listingB = $this->heldListing($sellerB, available: 10);

        $staleA = $this->placeOrder($this->buyer(), $listingA, 2);
        $freshB = $this->placeOrder($this->buyer(), $listingB, 3);

        $this->backdateCreatedAt($staleA, now()->subHours(48));

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $staleA->fresh()->status);
        $this->assertSame(OrderStatus::Placed, $freshB->fresh()->status);
        $this->assertSame(0.0, (float) $listingA->fresh()->quantity_held);
        $this->assertSame(3.0, (float) $listingB->fresh()->quantity_held);
        $this->assertSame(10.0, (float) $listingB->fresh()->quantity_available);
        $this->assertSame(0, $this->notices($sellerB->id, NotificationType::OrderAwaitingConfirmation));
        $this->assertSame(0, $this->notices($sellerB->id, NotificationType::OrderCancelled));
    }

    public function test_sweep_skips_an_order_confirmed_mid_sweep_and_still_cancels_the_other(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00:00', 'Asia/Manila'));

        $farmer = $this->farmer();
        $listingA = $this->heldListing($farmer);
        $listingB = $this->heldListing($farmer);
        $confirmed = $this->placeOrder($this->buyer(), $listingA, 1);
        $stale = $this->placeOrder($this->buyer(), $listingB, 1);

        $this->backdateCreatedAt($confirmed, now()->subHours(48));
        $this->backdateCreatedAt($stale, now()->subHours(48));

        $this->app->instance(OrderStateMachine::class, new class(app(InAppNotifier::class), $confirmed->id, $farmer) extends OrderStateMachine
        {
            public function __construct(
                InAppNotifier $notifier,
                private int $confirmId,
                private User $farmer,
            ) {
                parent::__construct($notifier);
            }

            public function transition(
                Order $order,
                OrderStatus $next,
                User|OrderActor $actor,
                ?string $note = null,
                ?CancellationReason $reason = null,
                ?float $amountReceived = null,
            ): Order {
                if ($actor === OrderActor::System && $order->id === $this->confirmId && $order->fresh()->status === OrderStatus::Placed) {
                    parent::transition($order, OrderStatus::Confirmed, $this->farmer);
                }

                return parent::transition($order, $next, $actor, $note, $reason, $amountReceived);
            }
        });

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $this->assertSame(OrderStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $stale->fresh()->status);
        $this->assertSame(CancellationReason::SellerUnresponsive, $stale->fresh()->cancellation_reason);
    }

    private function heldListing(User $farmer, float $available = 10): Listing
    {
        return $this->listingFor($farmer, [
            'price_per_unit' => 30,
            'quantity_available' => $available,
            'quantity_held' => 0,
        ]);
    }

    private function backdateCreatedAt(Order $order, Carbon $createdAt): void
    {
        Order::query()->whereKey($order->id)->update([
            'created_at' => $createdAt,
        ]);
    }

    private function notices(int $userId, NotificationType $type): int
    {
        return User::query()->findOrFail($userId)
            ->inAppNotifications()
            ->where('type', $type)
            ->count();
    }
}
