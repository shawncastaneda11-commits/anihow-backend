<?php

namespace Tests\Feature;

use App\Actions\Listings\EditFarmListing;
use App\Enums\ListingUnit;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Filament\Resources\Listings\Actions\FarmListingActions;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CmsListingStockTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_content_editor_adds_stock_as_a_harvest_and_the_seller_is_told(): void
    {
        [$editor, $seller, $listing] = $this->farmListing(stock: 10);

        $updated = app(EditFarmListing::class)->addStock($editor, $listing, [
            'quantity_harvested' => 6,
            'quantity_rejected' => 1,
            'rejection_reason' => 'pests',
            'harvested_on' => now()->toDateString(),
        ]);

        $this->assertEquals(15, (float) $updated->quantity_available);
        $this->assertTrue(InAppNotification::query()
            ->where('user_id', $seller->id)
            ->where('type', NotificationType::ListingEditedByFarm)
            ->where('body', 'like', '%added 5.00 kg of stock%')
            ->exists());
    }

    public function test_stock_held_by_orders_cannot_be_removed(): void
    {
        [$editor, , $listing] = $this->farmListing(stock: 10);
        $this->placeOrder($this->buyer(), $listing, 4);

        try {
            app(EditFarmListing::class)->removeStock($editor, $listing->fresh(), ['quantity' => 8, 'reason' => 'spoiled']);
            $this->fail('Stock held by an order was removed.');
        } catch (ValidationException) {
            $this->assertEquals(10, (float) $listing->fresh()->quantity_available);
        }

        app(EditFarmListing::class)->removeStock($editor, $listing->fresh(), ['quantity' => 6, 'reason' => 'spoiled']);
        $this->assertEquals(4, (float) $listing->fresh()->quantity_available);
    }

    public function test_a_correction_needs_a_note_and_the_panel_no_longer_types_over_stock(): void
    {
        [$editor, , $listing] = $this->farmListing(stock: 10);

        $this->expectException(ValidationException::class);

        try {
            app(EditFarmListing::class)->removeStock($editor, $listing, ['quantity' => 1, 'reason' => 'correction']);
        } finally {
            $this->assertArrayNotHasKey('quantity_available', FarmListingActions::formData($listing));
            $this->assertStringContainsString('Stock: 10', FarmListingActions::stockText($listing));
        }
    }

    /**
     * @return array{0: User, 1: User, 2: Listing}
     */
    private function farmListing(float $stock): array
    {
        $farm = $this->farm();
        $editor = User::factory()->create(['farm_id' => $farm->id]);
        $editor->syncRoles(Role::ContentEditor);
        $seller = $this->farmer([], $farm);
        $crop = $this->cropType(['unit_of_measure' => ListingUnit::Kilogram, 'floor_price' => 20]);
        $listing = $this->listingFor($seller, [
            'crop_type_id' => $crop->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 50,
            'quantity_available' => $stock,
            'quantity_held' => 0,
            'min_order_quantity' => 1,
            'order_step' => 1,
        ]);

        return [$editor, $seller, $listing];
    }
}
