<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\CropCareArticle;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * One account for selling and buying: a farmer-seller can turn on buying in
 * the app, or COSEL can in the CMS, and never buys their own listings.
 */
class SellerAlsoBuysTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_farmer_seller_turns_on_buying_in_the_app(): void
    {
        $seller = $this->farmer();

        $this->asUser($seller)
            ->postJson('/api/me/buying')
            ->assertOk()
            ->assertJsonPath('data.also_buys', true);

        $seller->refresh();
        $this->assertTrue($seller->isFarmerSeller());
        $this->assertTrue($seller->isBuyer());
        $this->assertSame('app', $seller->buying_enabled_via);
        $this->assertTrue($seller->requires_id_verification);
        $this->assertSame(Role::FarmerSeller, $seller->primaryRole());
        $this->assertTrue(ActivityLog::query()->where('action', 'buying_turned_on')->exists());
    }

    public function test_a_buyer_cannot_use_it(): void
    {
        $this->asUser($this->buyer())
            ->postJson('/api/me/buying')
            ->assertForbidden();
    }

    public function test_a_seller_who_buys_cannot_buy_or_message_their_own_stall(): void
    {
        $seller = $this->farmer();
        $seller->turnOnBuying('app');
        $own = $this->listingFor($seller);
        $other = $this->listingFor($this->farmer());

        $this->asUser($seller)
            ->postJson('/api/buyer/cart', ['listing_id' => $own->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');

        $this->asUser($seller)
            ->postJson('/api/buyer/cart', ['listing_id' => $other->id, 'quantity' => 1])
            ->assertCreated();

        $this->asUser($seller)
            ->getJson("/api/buyer/marketplace/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.is_own', true);

        $this->asUser($seller)
            ->postJson('/api/stall-chats', ['farmer_seller_id' => $seller->id])
            ->assertUnprocessable();
    }

    public function test_a_seller_who_buys_still_reads_crop_care(): void
    {
        $seller = $this->farmer();
        $seller->turnOnBuying('app');
        $article = CropCareArticle::factory()->create();

        $this->assertTrue($seller->fresh()->can('view', $article));
    }

    public function test_cosel_turns_buying_on_and_off_and_saving_keeps_it(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $cosel = User::factory()->create();
        $cosel->syncRoles(Role::AccountManager);
        $seller = $this->farmer(['phone' => '09170000000']);

        $page = Livewire::actingAs($cosel)
            ->test(ListUsers::class)
            ->call('openAccount', $seller->id)
            ->callAction('turnOnBuying');

        $this->assertTrue($seller->fresh()->alsoBuys());
        $this->assertSame('cms', $seller->fresh()->buying_enabled_via);

        // Saving the panel replaces the role with the one chosen; buying stays.
        $page->call('openAccount', $seller->id)
            ->set('account.phone', '09171112222')
            ->call('saveAccount')
            ->assertHasNoErrors();

        $this->assertTrue($seller->fresh()->alsoBuys());

        $page->call('openAccount', $seller->id)->callAction('turnOffBuying');

        $seller->refresh();
        $this->assertFalse($seller->isBuyer());
        $this->assertTrue($seller->isFarmerSeller());
        $this->assertNull($seller->buying_enabled_at);
    }
}
