<?php

namespace Tests\Feature\Api;

use App\Enums\ListingUnit;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FarmerListingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake(config('anihow.listing_disk'));
    }

    public function test_farmer_can_create_update_toggle_and_delete_own_listing(): void
    {
        $farmer = $this->farmer();
        $category = Category::factory()->create();
        $token = $farmer->createToken('mobile')->plainTextToken;

        $create = $this->withToken($token)->post('/api/farmer/listings', [
            'name' => 'Tomato',
            'category_id' => $category->id,
            'unit' => ListingUnit::Kilogram->value,
            'price_per_unit' => 65,
            'quantity_available' => 20,
            'description' => 'Fresh tomatoes',
            'image' => UploadedFile::fake()->image('tomato.jpg'),
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Tomato')
            ->assertJsonPath('data.is_active', true);

        $listingId = $create->json('data.id');
        Storage::disk(config('anihow.listing_disk'))->assertExists(Listing::query()->find($listingId)->image_path);

        $this->withToken($token)
            ->getJson('/api/farmer/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->patchJson("/api/farmer/listings/{$listingId}", [
                'price_per_unit' => 70,
            ])
            ->assertOk()
            ->assertJsonPath('data.price_per_unit', '70.00');

        $this->withToken($token)
            ->patchJson("/api/farmer/listings/{$listingId}/active", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)
            ->deleteJson("/api/farmer/listings/{$listingId}")
            ->assertOk();

        $this->assertDatabaseMissing('listings', ['id' => $listingId]);
    }

    public function test_farmer_cannot_update_another_farmers_listing(): void
    {
        $owner = $this->farmer(['email' => 'owner@example.com']);
        $intruder = $this->farmer(['email' => 'intruder@example.com']);
        $listing = Listing::factory()->forFarmer($owner)->create();

        $this->withToken($intruder->createToken('mobile')->plainTextToken)
            ->patchJson("/api/farmer/listings/{$listing->id}", [
                'name' => 'Stolen',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'name' => $listing->name,
        ]);
    }

    public function test_buyer_cannot_create_a_listing(): void
    {
        $buyer = User::factory()->create();
        $buyer->assignRole(Role::Buyer);
        $category = Category::factory()->create();

        $this->withToken($buyer->createToken('mobile')->plainTextToken)
            ->postJson('/api/farmer/listings', [
                'name' => 'Tomato',
                'category_id' => $category->id,
                'unit' => ListingUnit::Kilogram->value,
                'price_per_unit' => 65,
                'quantity_available' => 20,
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function farmer(array $attributes = []): User
    {
        $farmer = User::factory()->create($attributes);
        $farmer->assignRole(Role::FarmerSeller);

        return $farmer;
    }
}
