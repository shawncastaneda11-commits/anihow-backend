<?php

namespace Tests\Feature;

use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Enums\Role;
use App\Filament\Auth\MyAccount;
use App\Filament\Pages\Dashboard;
use App\Models\Farm;
use App\Models\IdVerification;
use App\Models\User;
use App\Support\Dashboard\HomeSummary;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * Each office lands on its own home screen; analytics and "My account" have
 * their own pages.
 */
class HomeScreensTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cosel_lands_on_what_is_waiting(): void
    {
        $this->farm();

        $this->actingAs($this->staff(Role::AccountManager))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Things waiting for you')
            ->assertSee('ID checks waiting')
            ->assertSee('Partner farms')
            ->assertDontSee('System health');
    }

    public function test_ictd_lands_on_the_system_overview(): void
    {
        $this->actingAs($this->staff(Role::SuperAdmin))
            ->get('/admin')
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Create administrator account')
            ->assertDontSee('Things waiting for you');
    }

    public function test_a_content_editor_lands_on_their_farm(): void
    {
        $farm = $this->farm();
        $this->farmer(['name' => 'Seller Without Listing'], $farm);

        $this->actingAs($this->staff(Role::ContentEditor, $farm))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Needs a look')
            ->assertSee('This month')
            ->assertSee('Seller Without Listing')
            ->assertDontSee('Things waiting for you');
    }

    public function test_the_cosel_queue_counts_waiting_id_checks(): void
    {
        foreach ([IdVerificationStatus::Pending, IdVerificationStatus::Approved] as $status) {
            IdVerification::query()->create([
                'user_id' => $this->buyer()->id,
                'id_type' => IdDocumentType::NationalId,
                'status' => $status,
                'consented_at' => now(),
            ]);
        }

        $this->assertSame(1, HomeSummary::coselQueue()['id_checks']);
    }

    public function test_the_editor_queue_counts_sellers_with_no_listing(): void
    {
        $farm = $this->farm();
        $selling = $this->farmer([], $farm);
        $this->farmer([], $farm);
        $this->listingFor($selling, ['is_active' => true]);

        $queue = HomeSummary::editorQueue($farm->id);

        $this->assertSame(1, $queue['sellers_without_listing']);
        $this->assertSame(1, $queue['listings_shown']);
    }

    public function test_analytics_and_my_account_have_their_own_pages(): void
    {
        $editor = $this->staff(Role::ContentEditor, $this->farm());

        $this->actingAs($editor)->get(Dashboard::getUrl(panel: 'admin'))->assertOk()->assertSee('Analytics');
        $this->actingAs($editor)->get(MyAccount::getUrl(panel: 'admin'))->assertOk()->assertSee('Two-step sign-in');
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
