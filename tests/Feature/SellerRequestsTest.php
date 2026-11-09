<?php

namespace Tests\Feature;

use App\Actions\Accounts\DecideSellerRequest;
use App\Actions\Accounts\RequestFarmerSellers;
use App\Enums\Role;
use App\Enums\SellerRequestStatus;
use App\Filament\Resources\SellerRequests\Pages\ListSellerRequests;
use App\Models\Farm;
use App\Models\SellerRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * A Content Editor asks COSEL to add farmer-sellers to their farm; COSEL
 * adds them (with a temporary password shown once) or says why not.
 */
class SellerRequestsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_content_editor_sends_requests_and_bad_lines_are_skipped(): void
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $this->buyer(['email' => 'taken@example.com']);

        $result = app(RequestFarmerSellers::class)->handle($editor, implode("\n", [
            'Name, Email, Mobile',
            'Maria Santos, maria@example.com',
            "Pedro Reyes\tpedro@example.com\t09171234567",
            'Ana Cruz',
            'Taken Person, taken@example.com',
        ]), 'Joined at the meeting.');

        $this->assertSame(['maria@example.com', 'pedro@example.com'], $result['sent']);
        $this->assertCount(2, $result['skipped']);
        $this->assertSame(2, SellerRequest::query()->where('farm_id', $farm->id)->where('status', SellerRequestStatus::Waiting)->count());

        $again = app(RequestFarmerSellers::class)->handle($editor, 'Maria Santos, maria@example.com');
        $this->assertSame([], $again['sent']);
    }

    public function test_cosel_adds_a_request_and_gets_a_temporary_password_once(): void
    {
        $farm = $this->farm();
        $cosel = $this->staff(Role::AccountManager);
        $request = $this->waiting($farm, 'maria@example.com');

        $result = app(DecideSellerRequest::class)->add($cosel, $request);

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $this->assertTrue($user->isFarmerSeller());
        $this->assertSame($farm->id, $user->farm_id);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertNotSame('', $result['password']);
        $this->assertSame(SellerRequestStatus::Added, $request->fresh()->status);
        $this->assertSame($user->id, $request->fresh()->user_id);
    }

    public function test_cosel_cannot_add_an_email_that_already_has_an_account_and_declines_instead(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $this->buyer(['email' => 'taken@example.com']);
        $request = $this->waiting($this->farm(), 'taken@example.com');

        try {
            app(DecideSellerRequest::class)->add($cosel, $request);
            $this->fail('An email that already has an account was added.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        app(DecideSellerRequest::class)->decline($cosel, $request, 'Ask them to use another email.');

        $this->assertSame(SellerRequestStatus::NotAdded, $request->fresh()->status);
        $this->assertSame('Ask them to use another email.', $request->fresh()->decision_reason);
    }

    public function test_a_content_editor_cannot_decide_requests(): void
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $request = $this->waiting($farm, 'maria@example.com');

        $this->expectException(HttpException::class);

        app(DecideSellerRequest::class)->add($editor, $request);
    }

    public function test_the_screens_follow_the_office(): void
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $own = $this->waiting($farm, 'own@example.com');
        $other = $this->waiting($this->farm(), 'other@example.com');

        Livewire::actingAs($editor)
            ->test(ListSellerRequests::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableActionHidden('addSeller', $own)
            ->callAction('askCosel', data: ['mode' => 'one', 'name' => 'Pedro Reyes', 'email' => 'pedro@example.com'])
            ->assertHasNoActionErrors();

        $this->assertTrue(SellerRequest::query()->where('email', 'pedro@example.com')->where('farm_id', $farm->id)->exists());

        Livewire::actingAs($this->staff(Role::AccountManager))
            ->test(ListSellerRequests::class)
            ->assertCanSeeTableRecords([$own, $other])
            ->assertActionHidden('askCosel')
            ->callTableAction('addSeller', $own)
            ->assertActionMounted('temporaryPassword');

        $this->assertSame(SellerRequestStatus::Added, $own->fresh()->status);
    }

    private function waiting(Farm $farm, string $email): SellerRequest
    {
        return SellerRequest::factory()->create([
            'farm_id' => $farm->id,
            'email' => $email,
            'status' => SellerRequestStatus::Waiting,
        ]);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
