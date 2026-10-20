<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class OrderViewActionTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_super_admin_can_open_an_order_from_the_list(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);
        $farmer = $this->farmer(['name' => 'Kuya Jun']);
        $order = $this->placeOrder($this->buyer(['name' => 'Buyer 01']), $this->listingFor($farmer), 2);

        $this->actingAs($admin, 'web');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListOrders::class)
            ->mountTableAction('view', $order)
            ->assertSee('Kuya Jun')
            ->assertSee('Buyer picks up');
    }
}
