<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\DeviceToken;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class PushSignInLinkTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_phone_registered_under_a_sign_in_stops_getting_pushes_when_it_ends(): void
    {
        $user = User::factory()->create();
        $signIn = $user->createToken('phone');

        $this->withToken($signIn->plainTextToken)
            ->postJson('/api/me/device-tokens', ['token' => 'phone-token'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'phone-token',
            'personal_access_token_id' => $signIn->accessToken->id,
        ]);

        // Sign out everywhere, a suspension or a password reset deletes the sign-ins.
        $user->tokens()->delete();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'phone-token']);
    }

    public function test_an_expired_sign_in_takes_its_phone_with_it_when_pruned(): void
    {
        $user = User::factory()->create();
        $signIn = $user->createToken('phone', ['*'], now()->addDay());
        DeviceToken::factory()->for($user)->create([
            'token' => 'old-phone',
            'personal_access_token_id' => $signIn->accessToken->id,
        ]);

        $this->travel(3)->days();
        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'old-phone']);
    }

    public function test_changing_the_password_signs_out_the_other_phones_and_keeps_this_one(): void
    {
        $user = $this->buyer(['password' => 'password123']);
        $mine = $user->createToken('my-phone');
        $theirs = $user->createToken('their-phone');
        DeviceToken::factory()->for($user)->create(['token' => 'my-push', 'personal_access_token_id' => $mine->accessToken->id]);
        DeviceToken::factory()->for($user)->create(['token' => 'their-push', 'personal_access_token_id' => $theirs->accessToken->id]);

        $this->withToken($mine->plainTextToken)
            ->postJson('/api/auth/password', [
                'current_password' => 'password123',
                'password' => 'New-Pass#123',
                'password_confirmation' => 'New-Pass#123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated. Your other phones were signed out.');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $mine->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $theirs->accessToken->id]);
        $this->assertDatabaseHas('device_tokens', ['token' => 'my-push']);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'their-push']);
    }

    public function test_suspension_and_sign_out_everywhere_end_pushes_registered_before_the_link(): void
    {
        $suspended = User::factory()->create();
        DeviceToken::factory()->for($suspended)->create(['token' => 'unlinked-1']);
        $suspended->update(['status' => UserStatus::Suspended, 'suspended_at' => now()]);

        $signedOut = User::factory()->create();
        DeviceToken::factory()->for($signedOut)->create(['token' => 'unlinked-2']);
        $signedOut->signOutEverywhere();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'unlinked-1']);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'unlinked-2']);
    }
}
