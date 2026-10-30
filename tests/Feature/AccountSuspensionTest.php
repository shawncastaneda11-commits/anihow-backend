<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_suspending_an_account_ends_its_app_sessions(): void
    {
        $farmer = $this->account(Role::FarmerSeller, Farm::factory()->create());
        $token = $farmer->createToken('phone')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/user')->assertOk();

        $farmer->update(['status' => UserStatus::Suspended, 'suspended_at' => now()]);

        $this->assertSame(0, $farmer->tokens()->count());
    }

    public function test_a_suspended_account_is_rejected_on_its_next_app_request(): void
    {
        $buyer = $this->account(Role::Buyer);
        $token = $buyer->createToken('phone')->plainTextToken;

        // Suspended without model events, as a direct database change would be.
        User::query()->whereKey($buyer->id)->update(['status' => UserStatus::Suspended]);

        $this->withToken($token)->getJson('/api/auth/user')
            ->assertUnauthorized()
            ->assertJson([
                'message' => EnsureAccountIsActive::MESSAGE,
                'code' => EnsureAccountIsActive::CODE,
            ]);

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_suspended_panel_user_is_signed_out_on_the_next_request(): void
    {
        $editor = $this->account(Role::ContentEditor, Farm::factory()->create());

        $this->actingAs($editor)->get('/admin')->assertOk();

        User::query()->whereKey($editor->id)->update(['status' => UserStatus::Suspended]);

        // A real request loads the account afresh from the database.
        $this->actingAs($editor->fresh())
            ->get('/admin')
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
        $this->assertGuest();
    }

    public function test_the_panel_checks_also_run_on_in_page_actions(): void
    {
        $this->assertContains(AuthenticatePanel::class, Livewire::getPersistentMiddleware());
    }

    private function account(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
