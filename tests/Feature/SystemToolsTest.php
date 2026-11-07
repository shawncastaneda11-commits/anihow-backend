<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\SystemHealthPage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AppVersion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * ICTD's switches: minimum app version, maintenance notice, app version
 * tracking, and signing a person out everywhere.
 */
class SystemToolsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_app_config_reports_the_minimum_version_and_maintenance(): void
    {
        $this->getJson('/api/app/config')
            ->assertOk()
            ->assertJson(['min_version' => null, 'maintenance' => false]);

        SystemSetting::put(SystemSetting::MIN_APP_VERSION, '1.0.3');
        SystemSetting::put(SystemSetting::MAINTENANCE_MESSAGE, 'Back at 5 PM.');

        $this->getJson('/api/app/config')
            ->assertOk()
            ->assertJson(['min_version' => '1.0.3', 'maintenance' => true, 'maintenance_message' => 'Back at 5 PM.']);
    }

    public function test_maintenance_answers_every_api_call_with_the_message(): void
    {
        SystemSetting::put(SystemSetting::MAINTENANCE_MESSAGE, 'Back at 5 PM.');

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'x'])
            ->assertStatus(503)
            ->assertJson(['message' => 'Back at 5 PM.', 'maintenance' => true]);
    }

    public function test_an_app_below_the_minimum_version_is_asked_to_update(): void
    {
        SystemSetting::put(SystemSetting::MIN_APP_VERSION, '1.0.3');
        $buyer = $this->buyer();

        $this->asUser($buyer)->withHeader('X-App-Version', '1.0.2')
            ->getJson('/api/crop-types')
            ->assertStatus(426)
            ->assertJson(['update_required' => true, 'min_version' => '1.0.3']);

        $this->asUser($buyer)->withHeader('X-App-Version', '1.0.3')
            ->getJson('/api/crop-types')
            ->assertOk();

        // Apps from before 1.0.3 send no version and are let through.
        $this->asUser($buyer)->getJson('/api/crop-types')->assertOk();
    }

    public function test_the_app_version_is_recorded_for_the_signed_in_user(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)->withHeader('X-App-Version', '1.0.3+4')
            ->getJson('/api/crop-types')
            ->assertOk();

        $this->assertSame('1.0.3', $buyer->fresh()->last_app_version);
        $this->assertNotNull($buyer->fresh()->last_app_seen_at);
    }

    public function test_versions_compare_numerically(): void
    {
        $this->assertTrue(AppVersion::isOlder('1.0.9', '1.0.10'));
        $this->assertFalse(AppVersion::isOlder('1.0.10', '1.0.9'));
        $this->assertFalse(AppVersion::isOlder(null, '1.0.3'));
        $this->assertFalse(AppVersion::isOlder('1.0.2', null));
    }

    public function test_sign_out_everywhere_ends_app_sessions(): void
    {
        $buyer = $this->buyer();
        $buyer->createToken('mobile');
        $buyer->createToken('tablet');

        $buyer->signOutEverywhere();

        $this->assertSame(0, $buyer->tokens()->count());
    }

    public function test_only_ictd_opens_the_system_health_page(): void
    {
        $ictd = User::factory()->create();
        $ictd->syncRoles(Role::SuperAdmin);
        $cosel = User::factory()->create();
        $cosel->syncRoles(Role::AccountManager);

        $this->actingAs($ictd);
        $this->assertTrue(SystemHealthPage::canAccess());

        $this->actingAs($cosel);
        $this->assertFalse(SystemHealthPage::canAccess());
    }
}
