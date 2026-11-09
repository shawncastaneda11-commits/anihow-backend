<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Filament\Pages\SellerAnalytics;
use App\Models\Farm;
use App\Models\Order;
use App\Models\User;
use App\Support\Analytics\SellerFigures;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * A farm's Content Editor sees the farm's figures one seller at a time;
 * no other office does.
 */
class SellerAnalyticsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_content_editor_sees_each_sellers_completed_sales(): void
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $seller = $this->farmer(['name' => 'Nena Villanueva'], $farm);
        $quiet = $this->farmer(['name' => 'Kuya Jun'], $farm);
        $elsewhere = $this->farmer(['name' => 'Other Farm Seller']);

        $completed = $this->completed($this->placeOrder($this->buyer(), $this->listingFor($seller)));
        $this->placeOrder($this->buyer(['email' => 'second.buyer@example.com']), $this->listingFor($seller));

        $rows = SellerFigures::rows($farm->id, '30')->keyBy(fn (array $row): int => $row['seller']->id);

        $this->assertEqualsWithDelta((float) $completed->total, $rows[$seller->id]['sales'], 0.001);
        $this->assertSame(1, $rows[$seller->id]['orders']);
        $this->assertSame(0, $rows[$quiet->id]['orders']);
        $this->assertArrayNotHasKey($elsewhere->id, $rows->all());

        Livewire::actingAs($editor)
            ->test(SellerAnalytics::class)
            ->assertSee('Nena Villanueva')
            ->assertSee('Kuya Jun')
            ->assertDontSee('Other Farm Seller')
            ->call('openSeller', $seller->id)
            ->assertSet('openId', $seller->id)
            ->assertSee('Completed orders')
            ->call('openSeller', $elsewhere->id)
            ->assertSet('openId', null);
    }

    public function test_only_a_farms_content_editor_can_open_it(): void
    {
        $this->actingAs($this->staff(Role::AccountManager));
        $this->assertFalse(SellerAnalytics::canAccess());

        $this->actingAs($this->staff(Role::SuperAdmin));
        $this->assertFalse(SellerAnalytics::canAccess());

        $this->actingAs($this->staff(Role::ContentEditor, $this->farm()));
        $this->assertTrue(SellerAnalytics::canAccess());
    }

    private function completed(Order $order): Order
    {
        $order->forceFill(['status' => OrderStatus::Completed, 'completed_at' => now()->subDay()])->saveQuietly();

        return $order->fresh();
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
