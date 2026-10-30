<?php

namespace Tests\Feature;

use App\Actions\Listings\GuardListingOpenOrders;
use App\Actions\Listings\TakeDownListingAction;
use App\Actions\Reports\ResolveReportAction;
use App\Enums\CancellationReason;
use App\Enums\ListingStatus;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Services\OrderStateMachine;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ListingTakedownOpenOrdersTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_takedown_with_open_orders_needs_confirmation(): void
    {
        $admin = $this->superAdmin();
        $listing = $this->stockedListing($farmer = $this->farmer());
        $this->placeOrder($this->buyer(), $listing, 2);

        try {
            app(TakeDownListingAction::class)->handle($listing, $admin, 'Not allowed.');
            $this->fail('The takedown went through without confirming the open orders.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirm_open_orders', $exception->errors());
        }

        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->assertSame(2.0, (float) $listing->fresh()->quantity_held);
    }

    public function test_a_confirmed_takedown_cancels_placed_orders_and_keeps_confirmed_ones(): void
    {
        $admin = $this->superAdmin();
        $farmer = $this->farmer();
        $listing = $this->stockedListing($farmer);
        $placed = $this->placeOrder($this->buyer(), $listing, 2);
        $confirmed = $this->placeOrder($this->buyer(), $listing, 3);
        app(OrderStateMachine::class)->transition($confirmed, OrderStatus::Confirmed, $farmer);

        app(TakeDownListingAction::class)->handle($listing, $admin, 'Not allowed.', confirmOpenOrders: true);

        $placed->refresh();
        $listing->refresh();

        $this->assertSame(ListingStatus::TakenDown, $listing->status);
        $this->assertSame(OrderStatus::Cancelled, $placed->status);
        $this->assertSame(CancellationReason::ListingTakenDown, $placed->cancellation_reason);
        $this->assertSame(OrderActor::System, $placed->cancelled_by);
        $this->assertSame(GuardListingOpenOrders::CANCELLATION_NOTE, $placed->cancellation_note);
        $this->assertSame(OrderStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(0.0, (float) $listing->quantity_held);
        $this->assertSame(17.0, (float) $listing->quantity_available);
    }

    public function test_a_takedown_without_open_orders_needs_no_extra_confirmation(): void
    {
        $listing = $this->stockedListing($this->farmer());

        app(TakeDownListingAction::class)->handle($listing, $this->superAdmin(), 'Not allowed.');

        $this->assertSame(ListingStatus::TakenDown, $listing->fresh()->status);
    }

    public function test_resolving_a_report_with_a_takedown_also_needs_the_confirmation(): void
    {
        $admin = $this->superAdmin();
        $listing = $this->stockedListing($this->farmer());
        $buyer = $this->buyer();
        $order = $this->placeOrder($buyer, $listing, 1);
        $report = Report::factory()->create([
            'reporter_id' => $buyer->id,
            'reportable_id' => $listing->id,
            'reportable_type' => Listing::class,
            'reason' => ReportReason::ProhibitedItem,
            'status' => ReportStatus::Open,
        ]);

        try {
            app(ResolveReportAction::class)->handle($report, $admin, 'Taken down.', true, 'Not allowed.');
            $this->fail('The report closed without confirming the open orders.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirm_open_orders', $exception->errors());
        }

        $this->assertSame(ReportStatus::Open, $report->fresh()->status);

        app(ResolveReportAction::class)->handle($report, $admin, 'Taken down.', true, 'Not allowed.', confirmOpenOrders: true);

        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_the_listings_table_takedown_asks_for_the_open_order_confirmation(): void
    {
        $admin = $this->superAdmin();
        $listing = $this->stockedListing($this->farmer());
        $this->placeOrder($this->buyer(), $listing, 1);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($admin);

        Livewire::test(ListListings::class)
            ->callTableAction('takedown', $listing, data: ['takedown_reason' => 'Not allowed.'])
            ->assertHasTableActionErrors(['confirm_open_orders']);

        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);

        Livewire::test(ListListings::class)
            ->callTableAction('takedown', $listing, data: [
                'takedown_reason' => 'Not allowed.',
                'confirm_open_orders' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ListingStatus::TakenDown, $listing->fresh()->status);
    }

    private function stockedListing(User $farmer): Listing
    {
        return $this->listingFor($farmer, [
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'quantity_held' => 0,
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);

        return $admin;
    }
}
