<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolePermissionSeeder;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CmsPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cms_reset_email_links_to_the_filament_reset_page(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['email' => 'admin.reset@example.com']);
        $admin->syncRoles(Role::SuperAdmin);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Forgot password?');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $admin->email])
            ->call('request');

        Notification::assertSentTo($admin, FilamentResetPassword::class, function (FilamentResetPassword $notification) use ($admin): bool {
            $url = $notification->url;

            return str_contains($url, '/admin/password-reset/reset')
                && ! str_contains($url, '/reset-password?')
                && $url === Filament::getResetPasswordUrl($notification->token, $admin);
        });

        Notification::assertNotSentTo($admin, ResetPasswordNotification::class);
    }
}
