<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class AccountDeleteGuardTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_an_account_with_an_order_in_progress_is_not_deleted(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $this->placeOrder($buyer, $this->listingFor($farmer, ['quantity_available' => 10]), 2);

        $this->accounts()
            ->callAction(TestAction::make('delete')->table($farmer))
            ->assertNotified('Not deleted.');

        $this->assertFalse($farmer->fresh()->trashed());
        $this->assertSame($farmer->email, User::query()->findOrFail($farmer->id)->email);
    }

    public function test_bulk_delete_skips_accounts_with_orders_in_progress(): void
    {
        $busy = $this->buyer();
        $idle = $this->buyer();
        $this->placeOrder($busy, $this->listingFor($this->farmer(), ['quantity_available' => 10]), 1);

        $this->accounts()
            ->selectTableRecords([$busy->id, $idle->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertFalse($busy->fresh()->trashed());
        $this->assertTrue(User::withTrashed()->findOrFail($idle->id)->trashed());
    }

    public function test_deleting_an_account_cancels_its_reservations(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer);
        $asSeller = Reservation::factory()->create([
            'farmer_seller_id' => $farmer->id,
            'farm_id' => $farmer->farm_id,
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
        ]);
        $asBuyer = Reservation::factory()->create(['buyer_id' => $farmer->id]);

        $this->accounts()->callAction(TestAction::make('delete')->table($farmer));

        $this->assertTrue(User::withTrashed()->findOrFail($farmer->id)->trashed());
        $this->assertSame(ReservationStatus::Cancelled, $asSeller->fresh()->status);
        $this->assertSame(ReservationStatus::Cancelled, $asBuyer->fresh()->status);
    }

    private function accounts(): Testable
    {
        $cosel = User::factory()->create();
        $cosel->syncRoles(Role::AccountManager);

        // placeOrder() signs in through the app API; drop its token first.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->actingAs($cosel);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return Livewire::actingAs($cosel)->test(ListUsers::class);
    }
}
