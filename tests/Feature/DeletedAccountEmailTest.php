<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DeletedAccountEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_deleting_an_account_in_the_cms_frees_its_email_for_a_new_sign_up(): void
    {
        $cosel = $this->account(Role::AccountManager);
        $buyer = $this->account(Role::Buyer, 'maria@example.com');
        $buyer->createToken('phone');

        $this->actingAs($cosel);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($cosel)
            ->test(ListUsers::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($buyer));

        $deleted = User::withTrashed()->findOrFail($buyer->id);
        $this->assertTrue($deleted->trashed());
        $this->assertSame("deleted-{$buyer->id}@anihow.invalid", $deleted->email);
        $this->assertSame(0, $deleted->tokens()->count());

        $this->postJson('/api/auth/register', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'Gulay#2026',
            'password_confirmation' => 'Gulay#2026',
            'accept_terms' => true,
        ])->assertCreated();
    }

    public function test_forgot_password_and_sign_up_agree_once_an_account_is_deleted(): void
    {
        $this->account(Role::Buyer, 'jun@example.com')->delete();

        $this->postJson('/api/auth/forgot-password', ['email' => 'jun@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/register', [
            'name' => 'Jun Reyes',
            'email' => 'jun@example.com',
            'password' => 'Gulay#2026',
            'password_confirmation' => 'Gulay#2026',
            'accept_terms' => true,
        ])->assertCreated();
    }

    public function test_the_migration_frees_the_emails_of_accounts_deleted_before_the_fix(): void
    {
        $old = $this->account(Role::Buyer, 'nena@example.com');
        $active = $this->account(Role::Buyer, 'active@example.com');

        // Deleted without model events, as the old Delete button left it.
        DB::table('users')->where('id', $old->id)->update(['deleted_at' => now()]);

        (require database_path('migrations/2026_11_14_100000_release_emails_of_deleted_accounts.php'))->up();

        $this->assertSame("deleted-{$old->id}@anihow.invalid", User::withTrashed()->findOrFail($old->id)->email);
        $this->assertSame('active@example.com', $active->fresh()->email);
    }

    private function account(Role $role, ?string $email = null): User
    {
        $user = User::factory()->create($email === null ? [] : ['email' => $email]);
        $user->syncRoles($role);

        return $user;
    }
}
