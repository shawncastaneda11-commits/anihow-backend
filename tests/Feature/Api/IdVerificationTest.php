<?php

namespace Tests\Feature\Api;

use App\Actions\IdVerification\ReviewIdVerificationAction;
use App\Actions\Privacy\AnonymizeUserAction;
use App\Enums\IdVerificationStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Filament\Resources\IdVerifications\IdVerificationResource;
use App\Filament\Resources\IdVerifications\Pages\ListIdVerifications;
use App\Filament\Resources\IdVerifications\Tables\IdVerificationsTable;
use App\Models\IdVerification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class IdVerificationTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_a_new_sign_up_must_verify_before_checking_out_or_reserving(): void
    {
        $buyer = $this->signUp();
        $listing = $this->listingFor($this->farmer(), ['quantity_available' => 10]);
        $this->addToCart($buyer, $listing, 1);

        $this->checkout($buyer)
            ->assertForbidden()
            ->assertJsonPath('code', 'id_verification_required');
        $this->asUser($buyer)->postJson('/api/buyer/reservations', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'id_verification_required');

        $this->asUser($buyer)->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id_verification.required', true)
            ->assertJsonPath('data.id_verification.verified', false)
            ->assertJsonPath('data.id_verification.status', 'none');
    }

    public function test_accounts_made_before_this_change_are_never_blocked(): void
    {
        $buyer = $this->buyer();
        $listing = $this->listingFor($this->farmer(), ['quantity_available' => 10]);

        $this->placeOrder($buyer, $listing);

        $this->asUser($buyer)->getJson('/api/auth/user')
            ->assertJsonPath('data.id_verification.required', false)
            ->assertJsonPath('data.id_verification.status', 'none');
    }

    public function test_the_switch_turns_the_requirement_off(): void
    {
        config(['anihow.require_id_verification' => false]);
        $buyer = $this->signUp();
        $listing = $this->listingFor($this->farmer(), ['quantity_available' => 10]);

        $this->placeOrder($buyer, $listing);
    }

    public function test_submitting_needs_consent_a_photo_and_a_known_id_type(): void
    {
        $buyer = $this->signUp();

        $this->asUser($buyer)->postJson('/api/auth/id-verification', [
            'id_type' => 'library_card',
            'front' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['id_type', 'front', 'consent']);

        $this->submit($buyer)
            ->assertCreated()
            ->assertJsonPath('data.id_verification.status', 'pending')
            ->assertJsonPath('data.id_verification.id_type', 'national_id');

        $verification = $buyer->idVerifications()->firstOrFail();
        $this->assertNotNull($verification->consented_at);
        Storage::disk('local')->assertExists($verification->front_path);
        Storage::disk('local')->assertExists($verification->selfie_path);

        $this->submit($buyer)->assertUnprocessable()->assertJsonValidationErrors('front');
    }

    public function test_only_buyers_can_submit(): void
    {
        $this->submit($this->farmer())->assertForbidden();
    }

    public function test_approving_unlocks_orders_deletes_the_photos_and_notifies(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $buyer = $this->signUp();
        $this->submit($buyer)->assertCreated();
        $verification = $buyer->idVerifications()->firstOrFail();
        $paths = [$verification->front_path, $verification->selfie_path];

        $this->actingAs($admin)
            ->get(route('id-verifications.photo', [$verification, 'front']))
            ->assertOk();

        app(ReviewIdVerificationAction::class)->approve($admin, $verification);

        $verification->refresh();
        $this->assertSame(IdVerificationStatus::Approved, $verification->status);
        $this->assertNull($verification->front_path);
        $this->assertNull($verification->selfie_path);
        $this->assertSame($admin->id, $verification->reviewed_by);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertNotNull($buyer->fresh()->id_verified_at);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $buyer->id,
            'type' => NotificationType::IdVerificationApproved->value,
        ]);

        $this->actingAs($admin)
            ->get(route('id-verifications.photo', [$verification, 'front']))
            ->assertNotFound();

        $this->asUser($buyer)->getJson('/api/auth/user')
            ->assertJsonPath('data.id_verification.required', false)
            ->assertJsonPath('data.id_verification.verified', true)
            ->assertJsonPath('data.id_verification.status', 'approved');

        $this->placeOrder($buyer, $this->listingFor($this->farmer(), ['quantity_available' => 10]));
        $this->submit($buyer)->assertUnprocessable()->assertJsonValidationErrors('front');
    }

    public function test_rejecting_needs_a_reason_shows_it_and_lets_the_buyer_try_again(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $buyer = $this->signUp();
        $this->submit($buyer)->assertCreated();
        $verification = $buyer->idVerifications()->firstOrFail();

        try {
            app(ReviewIdVerificationAction::class)->reject($admin, $verification, '  ');
            $this->fail('A blank reason was accepted.');
        } catch (ValidationException) {
            $this->assertTrue($verification->fresh()->isPending());
        }

        $reason = IdVerificationsTable::rejectionText(['reason' => 'blurry', 'note' => null]);
        app(ReviewIdVerificationAction::class)->reject($admin, $verification, $reason);

        $this->assertNull($verification->fresh()->front_path);
        $this->assertNull($buyer->fresh()->id_verified_at);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $buyer->id,
            'type' => NotificationType::IdVerificationRejected->value,
        ]);

        $this->asUser($buyer)->getJson('/api/auth/user')
            ->assertJsonPath('data.id_verification.status', 'rejected')
            ->assertJsonPath('data.id_verification.rejection_reason', $reason);

        $this->submit($buyer)->assertCreated()->assertJsonPath('data.id_verification.status', 'pending');

        $this->expectException(ValidationException::class);
        app(ReviewIdVerificationAction::class)->approve($admin, $verification);
    }

    public function test_only_account_managers_can_review_or_see_the_photos(): void
    {
        $editor = $this->staff(Role::ContentEditor);
        $buyer = $this->signUp();
        $this->submit($buyer)->assertCreated();
        $verification = $buyer->idVerifications()->firstOrFail();

        $this->actingAs($editor)
            ->get(route('id-verifications.photo', [$verification, 'front']))
            ->assertForbidden();
        $this->assertFalse(IdVerificationResource::canAccess());

        $this->actingAs($this->staff(Role::SuperAdmin));
        $this->assertTrue(IdVerificationResource::canAccess());
        $this->assertSame('1', IdVerificationResource::getNavigationBadge());

        $this->expectException(AuthorizationException::class);
        app(ReviewIdVerificationAction::class)->approve($editor, $verification);
    }

    public function test_the_cms_page_lists_checks_and_rejects_with_a_preset_reason(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $buyer = $this->signUp();
        $this->submit($buyer)->assertCreated();
        $verification = $buyer->idVerifications()->firstOrFail();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($admin);

        Livewire::test(ListIdVerifications::class)
            ->assertCanSeeTableRecords([$verification])
            ->callTableAction('reject', $verification, data: ['reason' => 'other', 'note' => ''])
            ->assertHasTableActionErrors(['note' => 'required']);

        Livewire::test(ListIdVerifications::class)
            ->callTableAction('reject', $verification, data: ['reason' => 'expired', 'note' => ''])
            ->assertHasNoTableActionErrors();

        $this->assertSame(IdVerificationsTable::REJECTION_REASONS['expired'], $verification->fresh()->rejection_reason);
    }

    public function test_deleting_an_account_removes_its_id_photos(): void
    {
        $buyer = $this->signUp();
        $this->submit($buyer)->assertCreated();
        $verification = $buyer->idVerifications()->firstOrFail();

        app(AnonymizeUserAction::class)->handle($buyer->fresh());

        Storage::disk('local')->assertMissing($verification->front_path);
        $this->assertSame(0, IdVerification::query()->count());
    }

    private function signUp(): User
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Maria Santos',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Gulay#2026',
            'password_confirmation' => 'Gulay#2026',
            'accept_terms' => true,
        ])->assertCreated();

        $buyer = User::query()->latest('id')->firstOrFail();
        $this->assertTrue($buyer->requires_id_verification);
        $buyer->forceFill(['email_verified_at' => now()])->save();

        return $buyer;
    }

    private function submit(User $user): TestResponse
    {
        return $this->asUser($user)->post('/api/auth/id-verification', [
            'id_type' => 'national_id',
            'front' => UploadedFile::fake()->image('front.jpg', 60, 40),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 40, 60),
            'consent' => '1',
        ], ['Accept' => 'application/json']);
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles($role);

        return $user;
    }
}
