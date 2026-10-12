<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class TermsAndPasswordRulesTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sign_up_needs_the_terms_and_records_the_version(): void
    {
        $payload = [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'Gulay#2026',
            'password_confirmation' => 'Gulay#2026',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accept_terms');

        $this->postJson('/api/auth/register', [...$payload, 'accept_terms' => true])
            ->assertCreated()
            ->assertJsonPath('data.needs_terms', false);

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame(1, $user->terms_version);
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_a_password_needs_length_both_cases_a_number_and_a_symbol(): void
    {
        foreach (['Gu#2026', 'gulay#2026', 'GULAY#2026', 'Gulay#gulay', 'Gulay2026'] as $weak) {
            $this->postJson('/api/auth/register', [
                'name' => 'Weak',
                'email' => 'weak@example.com',
                'password' => $weak,
                'password_confirmation' => $weak,
                'accept_terms' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }

        $buyer = $this->buyer(['password' => 'Gulay#2026']);
        $this->asUser($buyer)->postJson('/api/auth/password', [
            'current_password' => 'Gulay#2026',
            'password' => 'gulay2027',
            'password_confirmation' => 'gulay2027',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_an_app_user_accepts_the_current_terms_and_is_asked_again_after_a_change(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.needs_terms', true);

        $this->asUser($buyer)->postJson('/api/auth/terms/accept', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accept_terms');

        $this->asUser($buyer)->postJson('/api/auth/terms/accept', ['accept_terms' => true])
            ->assertOk()
            ->assertJsonPath('data.needs_terms', false);

        config(['anihow.terms_version' => 2]);

        $this->asUser($buyer)->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.needs_terms', true);
    }

    public function test_sellers_are_asked_but_cms_staff_are_not(): void
    {
        $this->assertTrue($this->farmer()->needsTermsAcceptance());

        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin);
        $this->assertFalse($admin->needsTermsAcceptance());

        $editor = User::factory()->create();
        $editor->assignRole(Role::ContentEditor);
        $this->assertFalse($editor->needsTermsAcceptance());
    }

    public function test_a_remembered_login_is_extended_when_the_app_is_used(): void
    {
        $buyer = $this->buyer();
        $token = $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk()->json('token');

        $this->travel(20)->days();
        $this->withToken($token)->getJson('/api/auth/user')->assertOk();

        $expires = PersonalAccessToken::query()->firstOrFail()->expires_at;
        $this->assertEqualsWithDelta(
            now()->addDays((int) config('anihow.auth.remember_days'))->getTimestamp(),
            $expires->getTimestamp(),
            60,
        );
    }

    public function test_a_short_session_login_is_not_extended(): void
    {
        $buyer = $this->buyer();
        $token = $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => 'password',
            'remember' => false,
        ])->assertOk()->json('token');

        $before = PersonalAccessToken::query()->firstOrFail()->expires_at;
        $this->travel(2)->hours();
        $this->withToken($token)->getJson('/api/auth/user')->assertOk();

        $this->assertEquals($before, PersonalAccessToken::query()->firstOrFail()->expires_at);
    }
}
