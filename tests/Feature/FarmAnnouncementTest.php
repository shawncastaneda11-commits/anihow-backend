<?php

namespace Tests\Feature;

use App\Actions\Announcements\NotifyFarmAnnouncementRecipientsAction;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\InAppNotification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmAnnouncementTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_content_editor_can_create_and_manage_announcements_for_their_own_farm_only(): void
    {
        [$farmA, $farmB] = $this->twoFarms();
        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $editorB = $this->staff(Role::ContentEditor, $farmB);

        $own = FarmAnnouncement::factory()->forFarm($farmA, $editorA)->create([
            'title' => 'Harvest day Saturday',
        ]);
        $other = FarmAnnouncement::factory()->forFarm($farmB, $editorB)->create([
            'title' => 'Other farm notice',
        ]);

        $this->assertTrue(Gate::forUser($editorA)->allows('create', FarmAnnouncement::class));
        $this->assertTrue(Gate::forUser($editorA)->allows('view', $own));
        $this->assertTrue(Gate::forUser($editorA)->allows('update', $own));
        $this->assertTrue(Gate::forUser($editorA)->allows('delete', $own));
        $this->assertTrue(Gate::forUser($editorA)->denies('view', $other));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $other));
        $this->assertTrue(Gate::forUser($editorA)->denies('delete', $other));
    }

    public function test_a_content_editor_cannot_see_another_farms_announcements_in_the_panel(): void
    {
        [$farmA, $farmB] = $this->twoFarms();
        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $own = FarmAnnouncement::factory()->forFarm($farmA, $editorA)->create();
        $other = FarmAnnouncement::factory()->forFarm($farmB)->create();

        $this->actingAs($editorA);

        $visible = FarmAnnouncementResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visible->contains($own->id));
        $this->assertFalse($visible->contains($other->id));
    }

    public function test_a_super_admin_can_manage_announcements_for_any_farm(): void
    {
        [$farmA] = $this->twoFarms();
        $admin = $this->staff(Role::SuperAdmin);
        $announcement = FarmAnnouncement::factory()->forFarm($farmA)->create();

        $this->assertTrue(Gate::forUser($admin)->allows('create', FarmAnnouncement::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $announcement));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $announcement));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $announcement));

        $this->actingAs($admin);

        $this->assertTrue(FarmAnnouncementResource::getEloquentQuery()->pluck('id')->contains($announcement->id));
    }

    public function test_a_farmer_seller_only_receives_their_own_farms_announcements(): void
    {
        [$farmA, $farmB] = $this->twoFarms();
        $sellerA = $this->farmer(['email' => 'seller.a@example.com'], $farmA);
        $sellerB = $this->farmer(['email' => 'seller.b@example.com'], $farmB);
        FarmAnnouncement::factory()->forFarm($farmA)->members()->create(['title' => 'Farm A members']);
        FarmAnnouncement::factory()->forFarm($farmA)->public()->create(['title' => 'Farm A public']);
        FarmAnnouncement::factory()->forFarm($farmB)->public()->create(['title' => 'Farm B public']);

        $titlesA = collect($this->asUser($sellerA)->getJson('/api/farmer/announcements')->assertOk()->json('data'))
            ->pluck('title');
        $titlesB = collect($this->asUser($sellerB)->getJson('/api/farmer/announcements')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertTrue($titlesA->contains('Farm A members'));
        $this->assertTrue($titlesA->contains('Farm A public'));
        $this->assertFalse($titlesA->contains('Farm B public'));
        $this->assertTrue($titlesB->contains('Farm B public'));
        $this->assertFalse($titlesB->contains('Farm A members'));
        $this->assertFalse($titlesB->contains('Farm A public'));
    }

    public function test_expired_announcements_are_hidden_from_farmers_and_the_farm_page(): void
    {
        [$farmA] = $this->twoFarms();
        $seller = $this->farmer([], $farmA);
        $buyer = $this->buyer();
        FarmAnnouncement::factory()->forFarm($farmA)->public()->expired()->create([
            'title' => 'Old harvest day',
        ]);
        FarmAnnouncement::factory()->forFarm($farmA)->public()->create([
            'title' => 'Pickup moved',
        ]);

        $farmerTitles = collect($this->asUser($seller)->getJson('/api/farmer/announcements')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertTrue($farmerTitles->contains('Pickup moved'));
        $this->assertFalse($farmerTitles->contains('Old harvest day'));

        $this->asUser($buyer)
            ->getJson('/api/farms/'.$farmA->id)
            ->assertOk()
            ->assertJsonPath('data.announcements.0.title', 'Pickup moved')
            ->assertJsonMissing(['title' => 'Old harvest day']);
    }

    public function test_buyers_never_see_members_announcements_on_the_farm_page(): void
    {
        [$farmA] = $this->twoFarms();
        $this->farmer([], $farmA);
        $buyer = $this->buyer();
        FarmAnnouncement::factory()->forFarm($farmA)->members()->create([
            'title' => 'Members only briefing',
        ]);
        FarmAnnouncement::factory()->forFarm($farmA)->public()->create([
            'title' => 'Harvest day Saturday',
        ]);

        $this->asUser($buyer)
            ->getJson('/api/farms/'.$farmA->id)
            ->assertOk()
            ->assertJsonPath('data.announcements.0.title', 'Harvest day Saturday')
            ->assertJsonCount(1, 'data.announcements');

        $this->asUser($buyer)
            ->getJson('/api/farmer/announcements')
            ->assertForbidden();
    }

    public function test_creating_an_active_announcement_notifies_only_that_farms_farmer_sellers(): void
    {
        [$farmA, $farmB] = $this->twoFarms();
        $editor = $this->staff(Role::ContentEditor, $farmA);
        $sellerA = $this->farmer(['email' => 'seller.a@example.com'], $farmA);
        $sellerA2 = $this->farmer(['email' => 'seller.a2@example.com'], $farmA);
        $inactiveSeller = $this->farmer(['email' => 'seller.inactive@example.com', 'status' => UserStatus::Suspended], $farmA);
        $sellerB = $this->farmer(['email' => 'seller.b@example.com'], $farmB);
        $buyer = $this->buyer();

        $announcement = FarmAnnouncement::factory()->forFarm($farmA, $editor)->create([
            'title' => 'Harvest day Saturday',
            'body' => 'Bring crates by 6am.',
        ]);

        app(NotifyFarmAnnouncementRecipientsAction::class)->handle($announcement);

        $this->assertSame(1, $this->notices($sellerA->id));
        $this->assertSame(1, $this->notices($sellerA2->id));
        $this->assertSame(0, $this->notices($inactiveSeller->id));
        $this->assertSame(0, $this->notices($sellerB->id));
        $this->assertSame(0, $this->notices($buyer->id));
        $this->assertSame(0, $this->notices($editor->id));

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $sellerA->id,
            'type' => NotificationType::FarmAnnouncement->value,
            'title' => 'Harvest day Saturday',
            'related_id' => $announcement->id,
            'related_type' => $announcement->getMorphClass(),
        ]);
    }

    public function test_creating_an_inactive_announcement_does_not_notify(): void
    {
        [$farmA] = $this->twoFarms();
        $seller = $this->farmer([], $farmA);
        $expired = FarmAnnouncement::factory()->forFarm($farmA)->expired()->create();
        $upcoming = FarmAnnouncement::factory()->forFarm($farmA)->create([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
        ]);

        app(NotifyFarmAnnouncementRecipientsAction::class)->handle($expired);
        app(NotifyFarmAnnouncementRecipientsAction::class)->handle($upcoming);

        $this->assertSame(0, $this->notices($seller->id));
    }

    public function test_farmer_announcements_are_pinned_first_then_newest(): void
    {
        [$farmA] = $this->twoFarms();
        $seller = $this->farmer([], $farmA);
        FarmAnnouncement::factory()->forFarm($farmA)->create([
            'title' => 'Older unpinned',
            'is_pinned' => false,
            'created_at' => now()->subDay(),
        ]);
        FarmAnnouncement::factory()->forFarm($farmA)->create([
            'title' => 'Pinned notice',
            'is_pinned' => true,
            'created_at' => now()->subHours(2),
        ]);
        FarmAnnouncement::factory()->forFarm($farmA)->create([
            'title' => 'Newest unpinned',
            'is_pinned' => false,
            'created_at' => now(),
        ]);

        $this->asUser($seller)
            ->getJson('/api/farmer/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pinned notice')
            ->assertJsonPath('data.1.title', 'Newest unpinned')
            ->assertJsonPath('data.2.title', 'Older unpinned');
    }

    /**
     * @return array{0: Farm, 1: Farm}
     */
    private function twoFarms(): array
    {
        return [
            Farm::factory()->create(['name' => 'Farm A']),
            Farm::factory()->create(['name' => 'Farm B']),
        ];
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function notices(int $userId): int
    {
        return InAppNotification::query()
            ->where('user_id', $userId)
            ->where('type', NotificationType::FarmAnnouncement)
            ->count();
    }
}
