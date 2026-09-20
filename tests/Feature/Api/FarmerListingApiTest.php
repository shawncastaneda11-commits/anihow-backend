<?php

namespace Tests\Feature\Api;

use App\Enums\TawadType;
use App\Models\TawadRule;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerListingApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_old_listing_body_is_rejected(): void
    {
        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                'name' => 'Tomato',
                'category_id' => 1,
                'unit' => 'kg',
                'price_per_unit' => 65,
                'quantity_available' => 20,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'crop_type_id']);
    }

    public function test_farmer_can_create_and_update_a_listing_with_the_live_body(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType();

        $create = $this->asUser($farmer)->postJson('/api/farmer/listings', [
            'title' => 'Fresh kamatis, hand picked',
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'description' => 'Morning harvest.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Fresh kamatis, hand picked')
            ->assertJsonPath('data.crop_type.id', $cropType->id);

        $listingId = $create->json('data.id');

        $this->asUser($farmer)
            ->patchJson("/api/farmer/listings/{$listingId}", [
                'title' => 'Kamatis, bagong ani',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Kamatis, bagong ani')
            ->assertJsonPath('data.crop_type.id', $cropType->id);

        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'title' => 'Kamatis, bagong ani',
            'crop_type_id' => $cropType->id,
        ]);
    }

    public function test_seller_cannot_view_or_update_another_sellers_listing(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $intruder = $this->farmer(['email' => 'intruder@example.com']);
        $listing = $this->listingFor($owner, ['title' => 'Owner kamatis']);

        $this->asUser($intruder)
            ->getJson("/api/farmer/listings/{$listing->id}")
            ->assertForbidden();

        $this->asUser($intruder)
            ->patchJson("/api/farmer/listings/{$listing->id}", [
                'title' => 'Stolen',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'title' => 'Owner kamatis',
        ]);
    }

    public function test_buyer_cannot_create_a_listing(): void
    {
        $buyer = $this->buyer();
        $cropType = $this->cropType();

        $this->asUser($buyer)
            ->postJson('/api/farmer/listings', [
                'title' => 'Tomato',
                'crop_type_id' => $cropType->id,
                'price_per_unit' => 30,
                'quantity_available' => 20,
            ])
            ->assertForbidden();
    }

    public function test_both_tawad_rule_types_can_be_created(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30]);

        $this->asUser($farmer)
            ->postJson("/api/farmer/listings/{$listing->id}/tawad", [
                'type' => TawadType::Flat->value,
                'discount_amount' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', TawadType::Flat->value)
            ->assertJsonPath('data.discount_amount', 5);

        $this->asUser($farmer)
            ->postJson("/api/farmer/listings/{$listing->id}/tawad", [
                'type' => TawadType::MinimumQuantity->value,
                'discount_amount' => 12,
                'min_quantity' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', TawadType::MinimumQuantity->value)
            ->assertJsonPath('data.min_quantity', 4);

        $this->assertSame(1, $listing->tawadRules()->where('is_active', true)->count());
    }

    public function test_tawad_above_the_crop_type_ceiling_is_rejected(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType(['max_discount' => 20]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
        ]);

        $this->asUser($farmer)
            ->postJson("/api/farmer/listings/{$listing->id}/tawad", [
                'type' => TawadType::Flat->value,
                'discount_amount' => 20.01,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_amount');
    }

    public function test_tawad_that_breaches_the_floor_price_is_rejected(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType(['floor_price' => 25, 'max_discount' => 20]);
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
        ]);

        $this->asUser($farmer)
            ->postJson("/api/farmer/listings/{$listing->id}/tawad", [
                'type' => TawadType::Flat->value,
                'discount_amount' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_amount');
    }

    public function test_ending_a_tawad_rule_removes_it_from_the_listing(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30]);
        $rule = TawadRule::factory()->for($listing)->create();

        $this->asUser($farmer)
            ->deleteJson("/api/farmer/listings/{$listing->id}/tawad/{$rule->id}")
            ->assertOk();

        $this->assertFalse($rule->fresh()->is_active);
        $this->assertNotNull($rule->fresh()->ended_at);
        $this->assertNull($listing->fresh()->activeTawadRule);
    }
}
