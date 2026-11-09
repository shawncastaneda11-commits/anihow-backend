<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Farm;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * A Content Editor sees their own farm's orders, reservations and payment
 * status, read-only: buyers by first name, no screenshots or references.
 */
class CmsSalesScreensTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_content_editor_sees_only_their_farms_orders_with_the_buyers_first_name(): void
    {
        [$editor, $own, $other] = $this->ordersOnTwoFarms();

        Livewire::actingAs($editor)
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableColumnFormattedStateSet('buyer.name', 'Rosa', $own);

        $this->actingAs($editor);
        $this->assertTrue(OrderResource::canView($own));
        $this->assertFalse(OrderResource::canView($other));
    }

    public function test_cosel_still_sees_every_farm_and_full_names(): void
    {
        [, $own, $other] = $this->ordersOnTwoFarms();
        $cosel = $this->staff(Role::AccountManager);

        Livewire::actingAs($cosel)
            ->test(ListOrders::class)
            ->assertCanSeeTableRecords([$own, $other])
            ->assertTableColumnFormattedStateSet('buyer.name', 'Rosa Dela Cruz', $own);
    }

    public function test_a_content_editor_sees_payment_status_but_not_the_screenshot(): void
    {
        [$editor, $own, $other] = $this->ordersOnTwoFarms();
        $ownProof = $this->proofFor($own);
        $otherProof = $this->proofFor($other);

        Livewire::actingAs($editor)
            ->test(ListPayments::class)
            ->assertCanSeeTableRecords([$ownProof])
            ->assertCanNotSeeTableRecords([$otherProof])
            ->assertTableActionHidden('viewScreenshot', $ownProof);

        $this->actingAs($editor, 'web')
            ->get(route('payments.proofs.screenshot', $ownProof))
            ->assertForbidden();
    }

    public function test_a_content_editor_without_a_farm_sees_none_of_it(): void
    {
        $this->actingAs($this->staff(Role::ContentEditor));

        $this->assertFalse(OrderResource::canAccess());
        $this->assertFalse(ReservationResource::canAccess());
        $this->assertFalse(PaymentResource::canAccess());
    }

    /**
     * @return array{0: User, 1: Order, 2: Order}
     */
    private function ordersOnTwoFarms(): array
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $buyer = $this->buyer(['name' => 'Rosa Dela Cruz']);

        $own = $this->placeOrder($buyer, $this->listingFor($this->farmer([], $farm)));
        $other = $this->placeOrder($this->buyer(['email' => 'other.buyer@example.com']), $this->listingFor($this->farmer([], $this->farm())));

        return [$editor, $own, $other];
    }

    private function proofFor(Order $order): PaymentProof
    {
        return PaymentProof::factory()->create([
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'farmer_seller_id' => $order->farmer_seller_id,
            'amount' => $order->total,
            'screenshot_path' => 'payment-proofs/test.jpg',
        ]);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
