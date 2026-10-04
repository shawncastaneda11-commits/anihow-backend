<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\FarmFavorite;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class BuyerAnnouncementFeedTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_buyer_sees_only_public_active_announcements_from_active_farms(): void
    {
        $buyer = $this->buyer();
        $farm = Farm::factory()->create(['name' => 'Open Farm']);
        $visible = FarmAnnouncement::factory()->public()->forFarm($farm)->create([
            'title' => 'Market day',
            'body' => 'Bring a basket.',
        ]);
        FarmAnnouncement::factory()->members()->forFarm($farm)->create([
            'title' => 'Members only',
        ]);
        FarmAnnouncement::factory()->public()->forFarm($farm)->create([
            'title' => 'Still scheduled',
            'starts_at' => now()->addDay(),
        ]);
        FarmAnnouncement::factory()->public()->expired()->forFarm($farm)->create([
            'title' => 'Already ended',
        ]);
        FarmAnnouncement::factory()->public()->forFarm(Farm::factory()->inactive()->create())->create([
            'title' => 'Inactive farm',
        ]);

        $response = $this->asUser($buyer)
            ->getJson('/api/buyer/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.title', 'Market day')
            ->assertJsonPath('data.0.body', 'Bring a basket.')
            ->assertJsonPath('data.0.is_pinned', false)
            ->assertJsonPath('data.0.image_url', null)
            ->assertJsonPath('data.0.published_at', $visible->created_at->toIso8601String())
            ->assertJsonPath('data.0.farm.id', $farm->id)
            ->assertJsonPath('data.0.farm.name', 'Open Farm')
            ->assertJsonPath('data.0.farm.cover_url', null);

        $item = $response->json('data.0');
        $this->assertSame(
            ['id', 'title', 'body', 'is_pinned', 'image_url', 'published_at', 'farm'],
            array_keys($item),
        );
        $this->assertArrayNotHasKey('author_id', $item);
        $this->assertArrayNotHasKey('author', $item);
        $this->assertArrayNotHasKey('audience', $item);
        $this->assertArrayNotHasKey('notified_at', $item);
        $this->assertStringNotContainsString('author_id', $response->getContent());
        $this->assertStringNotContainsString('notified_at', $response->getContent());

        $started = FarmAnnouncement::factory()->public()->forFarm($farm)->create([
            'title' => 'Started earlier',
            'starts_at' => now()->subHour(),
        ]);

        $this->asUser($buyer)
            ->getJson('/api/buyer/announcements')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $started->id,
                'published_at' => $started->starts_at->toIso8601String(),
            ]);
    }

    public function test_pinned_announcements_come_before_newer_ones(): void
    {
        $buyer = $this->buyer();
        $farm = Farm::factory()->create();
        $pinned = FarmAnnouncement::factory()->public()->forFarm($farm)->create([
            'title' => 'Pinned notice',
            'is_pinned' => true,
        ]);
        $newer = FarmAnnouncement::factory()->public()->forFarm($farm)->create([
            'title' => 'Newest unpinned',
        ]);
        $older = FarmAnnouncement::factory()->public()->forFarm($farm)->create([
            'title' => 'Older unpinned',
        ]);

        FarmAnnouncement::query()->whereKey($pinned->id)->update(['created_at' => now()->subDays(3)]);
        FarmAnnouncement::query()->whereKey($older->id)->update(['created_at' => now()->subDays(2)]);
        FarmAnnouncement::query()->whereKey($newer->id)->update(['created_at' => now()->subHour()]);

        $this->asUser($buyer)
            ->getJson('/api/buyer/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Pinned notice')
            ->assertJsonPath('data.1.title', 'Newest unpinned')
            ->assertJsonPath('data.2.title', 'Older unpinned');
    }

    public function test_following_and_farm_filters_limit_the_feed(): void
    {
        $buyer = $this->buyer();
        $followed = Farm::factory()->create(['name' => 'Followed Farm']);
        $other = Farm::factory()->create(['name' => 'Other Farm']);
        FarmFavorite::factory()->create([
            'buyer_id' => $buyer->id,
            'farm_id' => $followed->id,
        ]);
        FarmAnnouncement::factory()->public()->forFarm($followed)->create([
            'title' => 'From a farm I follow',
        ]);
        FarmAnnouncement::factory()->public()->forFarm($other)->create([
            'title' => 'From another farm',
        ]);

        $this->asUser($buyer)
            ->getJson('/api/buyer/announcements?following=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'From a farm I follow')
            ->assertJsonPath('data.0.farm.name', 'Followed Farm');

        $this->asUser($buyer)
            ->getJson('/api/buyer/announcements?farm_id='.$other->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'From another farm');
    }

    public function test_only_buyers_can_read_the_feed(): void
    {
        $this->getJson('/api/buyer/announcements')->assertUnauthorized();

        $this->asUser($this->farmer())
            ->getJson('/api/buyer/announcements')
            ->assertForbidden();

        $editor = User::factory()->create(['farm_id' => Farm::factory()->create()->id]);
        $editor->syncRoles(Role::ContentEditor);

        $this->asUser($editor)
            ->getJson('/api/buyer/announcements')
            ->assertForbidden();
    }
}
