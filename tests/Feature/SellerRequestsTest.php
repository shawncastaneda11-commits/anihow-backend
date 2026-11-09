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

    public function test_a_content_editor_sends_from_the_panel_and_sees_only_their_farm(): void
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $own = $this->waiting($farm, 'own@example.com');
        $other = $this->waiting($this->farm(), 'other@example.com');

        $page = Livewire::actingAs($editor)->test(ListSellerRequests::class);

        $this->assertSame([$own->id], $page->instance()->myRequests()->pluck('id')->all());
        $this->assertNotContains($other->id, $page->instance()->myRequests()->pluck('id')->all());

        $page->set('name', 'Pedro Reyes')
            ->set('email', 'pedro@example.com')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->call('setMode', 'many')
            ->set('rows', "Ana Cruz\nLuz Ramos, luz@example.com");

        $preview = $page->instance()->preview()['people'];
        $this->assertSame('needs an email', $preview[0]['problem']);
        $this->assertNull($preview[1]['problem']);
        $this->assertFalse(SellerRequest::query()->where('email', 'luz@example.com')->exists());

        $page->call('send')->assertHasNoErrors();

        $this->assertTrue(SellerRequest::query()->where('email', 'pedro@example.com')->where('farm_id', $farm->id)->exists());
        $this->assertTrue(SellerRequest::query()->where('email', 'luz@example.com')->where('farm_id', $farm->id)->exists());
        $this->assertSame(3, SellerRequest::query()->where('farm_id', $farm->id)->count());
    }

    public function test_cosel_works_through_the_queue_and_sees_the_password_once(): void
    {
        $farm = $this->farm();
        $older = $this->waiting($farm, 'older@example.com');
        $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();
        $newer = $this->waiting($this->farm(), 'newer@example.com');

        $page = Livewire::actingAs($this->staff(Role::AccountManager))
            ->test(ListSellerRequests::class)
            ->assertSet('selectedId', $older->id)
            ->call('createAccount')
            ->assertHasNoErrors()
            ->assertSet('selectedId', null);

        $this->assertSame('older@example.com', $page->get('created')['email']);
        $this->assertNotSame('', $page->get('created')['password']);
        $this->assertSame(SellerRequestStatus::Added, $older->fresh()->status);

        $page->call('finishCreated')
            ->assertSet('created', null)
            ->assertSet('selectedId', $newer->id)
            ->call('startDecline')
            ->call('decline')
            ->assertHasErrors(['reason'])
            ->set('reason', 'Ask them to use another email.')
            ->call('decline')
            ->assertHasNoErrors()
            ->assertSet('selectedId', null);

        $this->assertSame(SellerRequestStatus::NotAdded, $newer->fresh()->status);
        $this->assertSame('Ask them to use another email.', $newer->fresh()->decision_reason);
    }

    public function test_cosel_is_told_when_the_email_already_has_an_account(): void
    {
        $this->buyer(['email' => 'taken@example.com']);
        $request = $this->waiting($this->farm(), 'taken@example.com');

        Livewire::actingAs($this->staff(Role::AccountManager))
            ->test(ListSellerRequests::class)
            ->assertSet('selectedId', $request->id)
            ->call('createAccount')
            ->assertHasErrors(['decision'])
            ->assertSet('created', null);

        $this->assertSame(SellerRequestStatus::Waiting, $request->fresh()->status);
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
