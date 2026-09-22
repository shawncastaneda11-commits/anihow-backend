<?php

namespace Tests\Feature;

use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Enums\Role;
use App\Filament\Resources\CropCareArticles\CropCareArticleResource;
use App\Filament\Resources\Farms\FarmResource;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CropCareArticle;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmCropTypeOverride;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * Two farms, each with its own content editor and farmer-seller.
 * SmokeTestSeeder puts both sellers on one farm, so it never checks this.
 */
class FarmIsolationTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_content_editor_cannot_read_another_farms_articles_in_the_panel(): void
    {
        $scene = $this->scene();

        $this->actingAs($scene['editorA']);

        $visible = CropCareArticleResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visible->contains($scene['articleA']->id));
        $this->assertTrue($visible->contains($scene['draftA']->id));
        $this->assertFalse($visible->contains($scene['articleB']->id));
        $this->assertFalse($visible->contains($scene['draftB']->id));
    }

    public function test_a_content_editor_cannot_read_another_farms_draft_article(): void
    {
        $scene = $this->scene();

        $this->assertTrue(Gate::forUser($scene['editorA'])->denies('view', $scene['draftB']));

        $this->asUser($scene['editorA'])
            ->getJson('/api/crop-care/'.$scene['draftB']->id)
            ->assertForbidden();
    }

    public function test_a_content_editor_can_read_another_farms_published_article(): void
    {
        $scene = $this->scene();

        $this->assertTrue(Gate::forUser($scene['editorA'])->allows('view', $scene['articleB']));

        $listed = collect($this->asUser($scene['editorA'])->getJson('/api/crop-care')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($listed->contains($scene['articleB']->id));

        $this->asUser($scene['editorA'])
            ->getJson('/api/crop-care/'.$scene['articleB']->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Farm B care');
    }

    public function test_a_content_editor_cannot_write_or_unpublish_another_farms_article(): void
    {
        $scene = $this->scene();
        $editorA = $scene['editorA'];
        $articleB = $scene['articleB'];

        $this->assertTrue(Gate::forUser($editorA)->allows('update', $scene['articleA']));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $articleB));
        $this->assertTrue(Gate::forUser($editorA)->denies('delete', $articleB));
        $this->assertTrue(Gate::forUser($editorA)->denies('moderate', $articleB));
        $this->assertFalse($editorA->can('moderate_articles'));

        $this->assertTrue($articleB->fresh()->isPublished());
    }

    public function test_a_content_editor_cannot_read_or_write_another_farms_price_overrides(): void
    {
        $scene = $this->scene();
        $editorA = $scene['editorA'];

        $this->actingAs($editorA);

        $farms = FarmResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($farms->contains($scene['farmA']->id));
        $this->assertFalse($farms->contains($scene['farmB']->id));
        $this->assertTrue(Gate::forUser($editorA)->denies('view', $scene['overrideB']));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $scene['overrideB']));
        $this->assertTrue(Gate::forUser($editorA)->denies('manageForFarm', [FarmCropTypeOverride::class, $scene['farmB']]));
        $this->assertTrue(Gate::forUser($editorA)->allows('manageForFarm', [FarmCropTypeOverride::class, $scene['farmA']]));

        $this->assertEquals(35.0, (float) $scene['overrideB']->fresh()->floor_price);
    }

    public function test_a_farmer_seller_cannot_see_another_farms_listings_orders_or_figures(): void
    {
        $scene = $this->scene();
        $sellerA = $scene['sellerA'];

        $listings = collect($this->asUser($sellerA)->getJson('/api/farmer/listings')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($listings->contains($scene['listingA']->id));
        $this->assertFalse($listings->contains($scene['listingB']->id));

        $this->asUser($sellerA)
            ->getJson('/api/farmer/listings/'.$scene['listingB']->id)
            ->assertForbidden();

        $orders = collect($this->asUser($sellerA)->getJson('/api/farmer/orders')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($orders->contains($scene['orderA']->id));
        $this->assertFalse($orders->contains($scene['orderB']->id));

        $this->asUser($sellerA)
            ->getJson('/api/farmer/orders/'.$scene['orderB']->id)
            ->assertForbidden();

        $figures = $this->figures($sellerA);

        $this->assertSame(1, $figures['orders']);
        $this->assertEquals(30.0, $figures['revenue']);
        $this->assertEquals(1.0, $figures['units']);
    }

    public function test_a_farm_scoped_analytics_query_returns_only_that_farms_completed_orders(): void
    {
        $scene = $this->scene();
        $figures = $this->figures($scene['editorA']);

        $this->assertSame(1, $figures['orders']);
        $this->assertEquals(30.0, $figures['revenue']);
        $this->assertEquals(1.0, $figures['units']);
        $this->assertEquals(30.0, $figures['period_revenue']);
    }

    public function test_a_super_admin_sees_both_farms_system_wide(): void
    {
        $scene = $this->scene();
        $admin = $scene['admin'];

        $this->actingAs($admin);

        $articles = CropCareArticleResource::getEloquentQuery()->pluck('id');
        $farms = FarmResource::getEloquentQuery()->pluck('id');
        $orders = OrderResource::getEloquentQuery()->pluck('id');
        $listings = ListingResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($articles->contains($scene['articleA']->id));
        $this->assertTrue($articles->contains($scene['articleB']->id));
        $this->assertTrue($farms->contains($scene['farmA']->id));
        $this->assertTrue($farms->contains($scene['farmB']->id));
        $this->assertTrue($orders->contains($scene['orderA']->id));
        $this->assertTrue($orders->contains($scene['orderB']->id));
        $this->assertTrue($listings->contains($scene['listingA']->id));
        $this->assertTrue($listings->contains($scene['listingB']->id));

        $figures = $this->figures($admin);

        $this->assertSame(2, $figures['orders']);
        $this->assertEquals(90.0, $figures['revenue']);
        $this->assertEquals(3.0, $figures['units']);
    }

    /**
     * @return array{
     *     farmA: Farm,
     *     farmB: Farm,
     *     editorA: User,
     *     sellerA: User,
     *     sellerB: User,
     *     admin: User,
     *     articleA: CropCareArticle,
     *     articleB: CropCareArticle,
     *     draftA: CropCareArticle,
     *     draftB: CropCareArticle,
     *     listingA: Listing,
     *     listingB: Listing,
     *     orderA: Order,
     *     orderB: Order,
     *     overrideB: FarmCropTypeOverride
     * }
     */
    private function scene(): array
    {
        $farmA = Farm::factory()->create(['name' => 'Farm A']);
        $farmB = Farm::factory()->create(['name' => 'Farm B']);
        $cropType = CropType::factory()->create([
            'floor_price' => 25,
            'max_discount' => 20,
        ]);
        $guardedCrop = CropType::factory()->create([
            'floor_price' => 25,
            'max_discount' => 20,
        ]);

        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $editorB = $this->staff(Role::ContentEditor, $farmB);
        $sellerA = $this->farmer(['email' => 'seller.a@example.com'], $farmA);
        $sellerB = $this->farmer(['email' => 'seller.b@example.com'], $farmB);
        $admin = $this->staff(Role::SuperAdmin);
        $buyer = $this->buyer();

        $articleA = CropCareArticle::factory()->forFarm($farmA, $editorA)->create(['title' => 'Farm A care']);
        $draftA = CropCareArticle::factory()->forFarm($farmA, $editorA)->draft()->create(['title' => 'Farm A draft']);
        $articleB = CropCareArticle::factory()->forFarm($farmB, $editorB)->create(['title' => 'Farm B care']);
        $draftB = CropCareArticle::factory()->forFarm($farmB, $editorB)->draft()->create(['title' => 'Farm B draft']);

        $overrideB = app(SetFarmPriceOverrideAction::class)->execute($farmB, $guardedCrop, 35, null);
        $this->assertInstanceOf(FarmCropTypeOverride::class, $overrideB);

        $listingA = $this->listingFor($sellerA, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'title' => 'Farm A kamatis',
        ]);
        $listingB = $this->listingFor($sellerB, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'title' => 'Farm B kamatis',
        ]);

        $orderA = $this->completeOrder($sellerA, $this->placeOrder($buyer, $listingA, 1), 30);
        $orderB = $this->completeOrder($sellerB, $this->placeOrder($buyer, $listingB, 2), 60);

        return [
            'farmA' => $farmA,
            'farmB' => $farmB,
            'editorA' => $editorA,
            'sellerA' => $sellerA,
            'sellerB' => $sellerB,
            'admin' => $admin,
            'articleA' => $articleA,
            'articleB' => $articleB,
            'draftA' => $draftA,
            'draftB' => $draftB,
            'listingA' => $listingA,
            'listingB' => $listingB,
            'orderA' => $orderA,
            'orderB' => $orderB,
            'overrideB' => $overrideB,
        ];
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    /**
     * @return array{orders: int, revenue: float, units: float, period_revenue: float}
     */
    private function figures(User $viewer): array
    {
        $analytics = app(AnalyticsService::class);
        $units = $analytics->unitsSoldPerCropType($viewer);
        $today = $analytics->salesPerPeriod($viewer)->firstWhere('period', now()->toDateString());

        return [
            'orders' => $analytics->averageDiscount($viewer)['orders'],
            'revenue' => round((float) $units->sum('revenue'), 2),
            'units' => round((float) $units->sum('units'), 2),
            'period_revenue' => round((float) ($today->revenue ?? 0), 2),
        ];
    }
}
