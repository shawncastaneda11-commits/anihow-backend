<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RememberMeTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_remember_me_issues_a_token_that_lasts_about_thirty_days(): void
    {
        $buyer = $this->buyer();

        $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk();

        $this->assertExpiresNear($buyer, now()->addDays((int) config('anihow.auth.remember_days')));
    }

    public function test_login_without_remember_issues_a_token_that_lasts_about_twelve_hours(): void
    {
        $buyer = $this->buyer();

        $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => 'password',
            'remember' => false,
        ])->assertOk();

        $this->assertExpiresNear($buyer, now()->addHours((int) config('anihow.auth.session_hours')));
    }

    public function test_omitting_remember_lasts_as_long_as_remember_me(): void
    {
        $buyer = $this->buyer();

        $this->postJson('/api/auth/login', [
            'email' => $buyer->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertExpiresNear($buyer, now()->addDays((int) config('anihow.auth.remember_days')));
    }

    public function test_a_new_buyer_receives_a_thirty_day_token(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Maria Buyer',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $buyer = User::query()->where('email', 'maria@example.com')->firstOrFail();

        $this->assertExpiresNear($buyer, now()->addDays((int) config('anihow.auth.remember_days')));
    }

    public function test_an_expired_token_cannot_read_the_current_user(): void
    {
        $buyer = $this->buyer();
        $token = $buyer->createToken('mobile', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/user')
            ->assertUnauthorized();
    }

    public function test_expired_sanctum_tokens_are_pruned_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'sanctum:prune-expired --hours=24'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);

        return $buyer;
    }

    private function assertExpiresNear(User $user, \DateTimeInterface $expected): void
    {
        $expiresAt = $user->tokens()->firstOrFail()->expires_at;

        $this->assertNotNull($expiresAt);
        $this->assertEqualsWithDelta($expected->getTimestamp(), $expiresAt->getTimestamp(), 60);
    }
}
