<?php

namespace Tests\Feature;

use App\Enums\CancellationReason;
use App\Enums\NotificationType;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OrderStateMachine;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class SuspendedSellerOrdersTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cosel_cancels_a_confirmed_order_of_a_suspended_seller(): void
    {
        [$farmer, $buyer, $order, $listing] = $this->confirmedOrder();
        $farmer->update(['status' => UserStatus::Suspended, 'suspended_at' => now()]);

        $this->ordersAs($this->staff(Role::AccountManager))
            ->call('openRecord', $order->id)
            ->callAction('cancelForSuspendedSeller', data: ['note' => 'Sorry for the trouble.'])
            ->assertNotified('Order cancelled. The buyer has been told.');

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(CancellationReason::SellerSuspended, $order->cancellation_reason);
        $this->assertSame(OrderActor::System, $order->cancelled_by);
        $this->assertSame(20.0, (float) $listing->fresh()->quantity_available);
        $this->assertTrue($buyer->inAppNotifications()
            ->where('type', NotificationType::OrderCancelled)
            ->where('body', 'like', '%seller\'s account is suspended%')
            ->exists());
    }

    public function test_the_order_cannot_be_cancelled_this_way_while_the_seller_is_active(): void
    {
        [, , $order] = $this->confirmedOrder();

        $this->ordersAs($cosel = $this->staff(Role::AccountManager))
            ->call('openRecord', $order->id)
            ->assertActionHidden('cancelForSuspendedSeller');

        $this->expectException(ValidationException::class);
        app(OrderStateMachine::class)->transition($order, OrderStatus::Cancelled, $cosel, reason: CancellationReason::SellerSuspended);
    }

    public function test_a_content_editor_cannot_cancel_a_suspended_sellers_order(): void
    {
        [$farmer, , $order] = $this->confirmedOrder();
        $farmer->update(['status' => UserStatus::Suspended, 'suspended_at' => now()]);
        $editor = $this->staff(Role::ContentEditor, $farmer->farm_id);

        $this->assertFalse($order->fresh()->canBeCancelledForSuspendedSeller($editor));
        $this->assertTrue($order->fresh()->canBeCancelledForSuspendedSeller($this->staff(Role::AccountManager)));
    }

    public function test_suspending_a_buyer_cancels_their_reservations(): void
    {
        $buyer = $this->buyer();
        $reservation = Reservation::factory()->create(['buyer_id' => $buyer->id]);
        $cosel = $this->staff(Role::AccountManager);

        $this->actingAs($cosel, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($cosel, 'web')
            ->test(ListUsers::class)
            ->callAction(TestAction::make('suspend')->table($buyer), data: ['suspension_reason' => 'Fake orders.']);

        $this->assertSame(UserStatus::Suspended, $buyer->fresh()->status);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
    }

    /**
     * @return array{0: User, 1: User, 2: Order, 3: Listing}
     */
    private function confirmedOrder(): array
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['quantity_available' => 20, 'quantity_held' => 0]);
        $order = $this->placeOrder($buyer, $listing, 2);
        app(OrderStateMachine::class)->transition($order, OrderStatus::Confirmed, $farmer);

        return [$farmer, $buyer, $order->fresh(), $listing];
    }

    private function staff(Role $role, ?int $farmId = null): User
    {
        $user = User::factory()->create(['farm_id' => $farmId]);
        $user->syncRoles($role);

        return $user;
    }

    private function ordersAs(User $user): Testable
    {
        // placeOrder() signs in through the app API; the CMS uses the web guard.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->actingAs($user, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::actingAs($user, 'web')->test(ListOrders::class);
    }
}
