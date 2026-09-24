<?php

namespace Tests\Feature;

use App\Actions\Privacy\ApproveAccountDeletionRequestAction;
use App\Actions\Privacy\RejectAccountDeletionRequestAction;
use App\Enums\AccountDeletionStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\AccountDeletionRequests\AccountDeletionRequestResource;
use App\Mail\AccountDeletionCompletedMail;
use App\Models\AccountDeletionRequest;
use App\Models\Farm;
use App\Models\Favorite;
use App\Models\InAppNotification;
use App\Models\Order;
use App\Models\TawadRule;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class PrivacyRightsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_profile_update_validates_and_ignores_email(): void
    {
        $buyer = $this->buyer([
            'name' => 'Old Name',
            'email' => 'buyer@example.com',
            'phone' => '09170000000',
            'location' => 'Rosario',
        ]);

        $this->asUser($buyer)
            ->patchJson('/api/auth/user', [
                'name' => '',
                'phone' => '09170001111',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->asUser($buyer)
            ->patchJson('/api/auth/user', [
                'name' => 'New Name',
                'phone' => '09170001111',
                'location' => 'General Trias',
                'email' => 'hijacked@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.phone', '09170001111')
            ->assertJsonPath('data.location', 'General Trias')
            ->assertJsonPath('data.email', 'buyer@example.com');

        $buyer->refresh();
        $this->assertSame('buyer@example.com', $buyer->email);
        $this->assertSame('New Name', $buyer->name);
    }

    public function test_content_editor_cannot_use_app_privacy_endpoints(): void
    {
        $editor = $this->staff(Role::ContentEditor);

        $this->asUser($editor)
            ->patchJson('/api/auth/user', ['name' => 'Editor'])
            ->assertForbidden();

        $this->asUser($editor)
            ->get('/api/auth/user/export')
            ->assertForbidden();

        $this->asUser($editor)
            ->postJson('/api/auth/user/deletion-request')
            ->assertForbidden();
    }

    public function test_export_contains_only_the_requester_data(): void
    {
        $farmer = $this->farmer([
            'name' => 'Mang Tonyo',
            'shop_name' => 'Tonyo Stall',
            'email' => 'seller-secret@example.com',
            'phone' => '09171112222',
        ]);
        $listing = $this->listingFor($farmer, [
            'title' => 'Kamatis',
            'price_per_unit' => 30,
            'quantity_available' => 40,
        ]);
        TawadRule::factory()->for($listing)->create(['discount_amount' => 5]);

        $buyer = $this->buyer([
            'name' => 'Ana Buyer',
            'email' => 'ana@example.com',
            'phone' => '09170001111',
        ]);
        $other = $this->buyer([
            'name' => 'Other Buyer',
            'email' => 'other-secret@example.com',
            'phone' => '09998887777',
        ]);

        $order = $this->completeOrder($farmer, $this->placeOrder($buyer, $listing, 1), 30);
        $this->completeOrder($farmer, $this->placeOrder($other, $listing, 1), 30);

        $this->asUser($buyer)->postJson('/api/buyer/reviews', [
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Sariwa.',
        ])->assertCreated();

        $this->asUser($buyer)->postJson('/api/buyer/favorites', [
            'listing_id' => $listing->id,
        ])->assertCreated();

        $this->addToCart($buyer, $listing, 2);

        $this->asUser($buyer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'See you at 7am.'])
            ->assertCreated();

        InAppNotification::factory()->create([
            'user_id' => $buyer->id,
            'title' => 'Your order is ready',
        ]);

        $response = $this->asUser($buyer)->get('/api/auth/user/export');
        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'anihow-my-data-'.now()->toDateString().'.json',
            (string) $response->headers->get('Content-Disposition'),
        );

        $payload = json_decode($response->streamedContent(), true);
        $encoded = json_encode($payload);

        $this->assertSame('ana@example.com', $payload['profile']['email']);
        $this->assertSame('09170001111', $payload['profile']['phone']);
        $this->assertCount(1, $payload['orders']);
        $this->assertSame('Tonyo Stall', $payload['orders'][0]['counterparty_name']);
        $this->assertNotEmpty($payload['reviews_written']);
        $this->assertNotEmpty($payload['favorites']);
        $this->assertNotEmpty($payload['cart']);
        $this->assertNotEmpty($payload['order_messages']);
        $this->assertNotEmpty($payload['notifications']);
        $this->assertSame([], $payload['listings']);
        $this->assertSame([], $payload['tawad_rules']);
        $this->assertStringNotContainsString('seller-secret@example.com', $encoded);
        $this->assertStringNotContainsString('other-secret@example.com', $encoded);
        $this->assertStringNotContainsString('09171112222', $encoded);
        $this->assertStringNotContainsString('09998887777', $encoded);

        $sellerExport = json_decode($this->asUser($farmer)->get('/api/auth/user/export')->streamedContent(), true);
        $sellerEncoded = json_encode($sellerExport);

        $this->assertSame('seller-secret@example.com', $sellerExport['profile']['email']);
        $this->assertNotEmpty($sellerExport['listings']);
        $this->assertNotEmpty($sellerExport['tawad_rules']);
        $this->assertCount(2, $sellerExport['orders']);
        $this->assertStringNotContainsString('ana@example.com', $sellerEncoded);
        $this->assertStringNotContainsString('other-secret@example.com', $sellerEncoded);
        $this->assertStringNotContainsString('09170001111', $sellerEncoded);
        $this->assertStringNotContainsString('09998887777', $sellerEncoded);
    }

    public function test_deletion_request_is_refused_when_orders_are_open(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->placeOrder($buyer, $listing, 1);

        $this->asUser($buyer)
            ->postJson('/api/auth/user/deletion-request', ['reason' => 'Leaving'])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.status.0',
                'You have open orders (placed, confirmed, or ready) that must be completed or cancelled before you can request account deletion.',
            );

        $this->asUser($farmer)
            ->postJson('/api/auth/user/deletion-request')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_only_one_pending_deletion_request_is_allowed_and_cancel_works(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $buyer = $this->buyer(['name' => 'Ana Buyer']);

        $this->asUser($buyer)
            ->getJson('/api/auth/user/deletion-request')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->asUser($buyer)
            ->postJson('/api/auth/user/deletion-request', ['reason' => 'Moving away'])
            ->assertCreated()
            ->assertJsonPath('data.status', AccountDeletionStatus::Pending->value);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $admin->id,
            'type' => NotificationType::AccountDeletionRequested->value,
        ]);

        $this->asUser($buyer)
            ->postJson('/api/auth/user/deletion-request')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'You already have a pending account deletion request.');

        $this->asUser($buyer)
            ->getJson('/api/auth/user/deletion-request')
            ->assertOk()
            ->assertJsonPath('data.status', AccountDeletionStatus::Pending->value);

        $this->asUser($buyer)
            ->deleteJson('/api/auth/user/deletion-request')
            ->assertOk()
            ->assertJsonPath('message', 'Account deletion request cancelled.');

        $this->asUser($buyer)
            ->getJson('/api/auth/user/deletion-request')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->asUser($buyer)
            ->postJson('/api/auth/user/deletion-request')
            ->assertCreated();
    }

    public function test_super_admin_approve_anonymises_and_preserves_the_ledger(): void
    {
        Mail::fake();

        $admin = $this->staff(Role::SuperAdmin);
        $farm = $this->farm();
        $farmer = $this->farmer([
            'name' => 'Mang Tonyo',
            'email' => 'tony@example.com',
            'phone' => '09171112222',
            'location' => 'Tejero',
            'shop_name' => 'Tonyo Stall',
            'bio' => 'Fresh produce',
            'contact' => '09171112222',
        ], $farm);
        $listing = $this->listingFor($farmer, [
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'is_active' => true,
        ]);
        $rule = TawadRule::factory()->for($listing)->create(['is_active' => true]);
        $buyer = $this->buyer();
        $order = $this->completeOrder($farmer, $this->placeOrder($buyer, $listing, 1), 30);

        $this->asUser($farmer)
            ->postJson("/api/orders/{$order->id}/messages", ['body' => 'Salamat.'])
            ->assertCreated();

        $this->asUser($buyer)->postJson('/api/buyer/favorites', ['listing_id' => $listing->id])->assertCreated();
        $this->addToCart($buyer, $listing, 1);
        InAppNotification::factory()->create(['user_id' => $farmer->id]);

        $this->asUser($farmer)->postJson('/api/auth/user/deletion-request')->assertCreated();
        $deletion = AccountDeletionRequest::query()->where('user_id', $farmer->id)->firstOrFail();

        $before = app(AnalyticsService::class)->completedSummary($admin);

        app(ApproveAccountDeletionRequestAction::class)->handle($admin, $deletion);

        Mail::assertSent(AccountDeletionCompletedMail::class, function (AccountDeletionCompletedMail $mail): bool {
            return $mail->hasTo('tony@example.com')
                && $mail->recipientName === 'Mang Tonyo';
        });

        $anonymised = User::withTrashed()->findOrFail($farmer->id);
        $this->assertTrue($anonymised->trashed());
        $this->assertSame('Deleted user', $anonymised->name);
        $this->assertSame("deleted-{$farmer->id}@anihow.invalid", $anonymised->email);
        $this->assertNull($anonymised->phone);
        $this->assertNull($anonymised->location);
        $this->assertNull($anonymised->shop_name);
        $this->assertNull($anonymised->bio);
        $this->assertNull($anonymised->contact);
        $this->assertSame(UserStatus::Suspended, $anonymised->status);
        $this->assertSame(0, $anonymised->tokens()->count());
        $this->assertFalse($listing->fresh()->is_active);
        $this->assertFalse($rule->fresh()->is_active);
        $this->assertNotNull($rule->fresh()->ended_at);
        $this->assertSame(0, $farmer->cartItems()->count());
        $this->assertSame(0, Favorite::query()->where('buyer_id', $farmer->id)->count());
        $this->assertSame(0, $anonymised->inAppNotifications()->count());

        $this->assertTrue(Order::query()->whereKey($order->id)->exists());
        $this->assertSame($farmer->id, $order->fresh()->farmer_seller_id);
        $this->assertSame($before, app(AnalyticsService::class)->completedSummary($admin));

        $this->postJson('/api/auth/login', [
            'email' => 'tony@example.com',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->asUser($buyer)
            ->getJson("/api/orders/{$order->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Salamat.')
            ->assertJsonPath('data.0.author.name', 'Deleted user');

        $this->assertSame(AccountDeletionStatus::Completed, $deletion->fresh()->status);
        $this->assertSame($admin->id, $deletion->fresh()->processed_by);
    }

    public function test_approve_aborts_when_open_orders_appear_after_the_request(): void
    {
        Mail::fake();

        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $this->asUser($farmer)->postJson('/api/auth/user/deletion-request')->assertCreated();
        $deletion = AccountDeletionRequest::query()->where('user_id', $farmer->id)->firstOrFail();

        $this->placeOrder($this->buyer(), $listing, 1);

        try {
            app(ApproveAccountDeletionRequestAction::class)->handle($admin, $deletion);
            $this->fail('Open orders should block anonymisation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertFalse($farmer->fresh()->trashed());
        $this->assertSame(AccountDeletionStatus::Pending, $deletion->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_reject_keeps_the_account_and_notifies(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $buyer = $this->buyer(['email' => 'keep-me@example.com']);
        $this->asUser($buyer)->postJson('/api/auth/user/deletion-request')->assertCreated();
        $deletion = AccountDeletionRequest::query()->where('user_id', $buyer->id)->firstOrFail();

        app(RejectAccountDeletionRequestAction::class)->handle($admin, $deletion, 'Finish your orders first.');

        $buyer->refresh();
        $this->assertFalse($buyer->trashed());
        $this->assertSame('keep-me@example.com', $buyer->email);
        $this->assertTrue($buyer->isActive());
        $this->assertSame(AccountDeletionStatus::Rejected, $deletion->fresh()->status);
        $this->assertSame('Finish your orders first.', $deletion->fresh()->rejection_note);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $buyer->id,
            'type' => NotificationType::AccountDeletionRejected->value,
        ]);
        $this->assertStringContainsString(
            'Finish your orders first.',
            InAppNotification::query()->where('user_id', $buyer->id)->where('type', NotificationType::AccountDeletionRejected)->value('body'),
        );
    }

    public function test_non_super_admins_cannot_process_deletion_requests(): void
    {
        $editor = $this->staff(Role::ContentEditor);
        $farmer = $this->farmer();
        $this->asUser($farmer)->postJson('/api/auth/user/deletion-request')->assertCreated();
        $deletion = AccountDeletionRequest::query()->where('user_id', $farmer->id)->firstOrFail();

        $this->actingAs($editor);
        $this->assertFalse(AccountDeletionRequestResource::canAccess());

        $this->expectException(AuthorizationException::class);
        app(ApproveAccountDeletionRequestAction::class)->handle($editor, $deletion);
    }

    public function test_farmer_cannot_process_another_users_deletion_request(): void
    {
        $sellerA = $this->farmer();
        $sellerB = $this->farmer();
        $this->asUser($sellerA)->postJson('/api/auth/user/deletion-request')->assertCreated();
        $deletion = AccountDeletionRequest::query()->where('user_id', $sellerA->id)->firstOrFail();

        $this->actingAs($sellerB);
        $this->assertFalse(AccountDeletionRequestResource::canAccess());

        $this->expectException(AuthorizationException::class);
        app(RejectAccountDeletionRequestAction::class)->handle($sellerB, $deletion, 'No.');
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
