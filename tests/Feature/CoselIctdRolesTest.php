<?php

namespace Tests\Feature;

use App\Actions\Accounts\BulkCreateFarmerSellers;
use App\Actions\Announcements\SendSystemAnnouncementAction;
use App\Actions\Exports\ExportCommunityImpactAction;
use App\Actions\Listings\EditFarmListing;
use App\Enums\ListingUnit;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * COSEL (Account Manager) manages people; ICTD (Super Admin) manages the
 * system and administrator accounts; a farm's Content Editor manages that
 * farm's listings.
 */
class CoselIctdRolesTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    // Accounts

    public function test_cosel_manages_people_but_not_administrators(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $ictd = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();

        $this->assertTrue($cosel->can('update', $farmer));
        $this->assertTrue($cosel->can('suspend', $farmer));
        $this->assertFalse($cosel->can('update', $ictd));
        $this->assertFalse($cosel->can('suspend', $ictd));
        $this->assertEqualsCanonicalizing(
            [Role::ContentEditor, Role::FarmerSeller, Role::Buyer],
            AccountAuthority::assignableRoles($cosel),
        );
    }

    public function test_ictd_manages_administrators_but_not_people(): void
    {
        $ictd = $this->staff(Role::SuperAdmin);
        $cosel = $this->staff(Role::AccountManager);
        $farmer = $this->farmer();

        $this->assertTrue($ictd->can('update', $cosel));
        $this->assertTrue($ictd->can('suspend', $cosel));
        $this->assertFalse($ictd->can('update', $farmer));
        $this->assertFalse($ictd->can('suspend', $farmer));
        $this->assertFalse($ictd->can('delete', $ictd));
        $this->assertEqualsCanonicalizing(
            [Role::AccountManager, Role::SuperAdmin],
            AccountAuthority::assignableRoles($ictd),
        );
    }

    public function test_each_office_sees_only_its_own_accounts(): void
    {
        $ictd = $this->staff(Role::SuperAdmin);
        $cosel = $this->staff(Role::AccountManager);
        $farmer = $this->farmer();

        $coselSees = AccountAuthority::scope(User::query(), $cosel)->pluck('id');
        $ictdSees = AccountAuthority::scope(User::query(), $ictd)->pluck('id');

        $this->assertTrue($coselSees->contains($farmer->id));
        $this->assertFalse($coselSees->contains($ictd->id));
        $this->assertTrue($ictdSees->contains($cosel->id));
        $this->assertFalse($ictdSees->contains($farmer->id));
    }

    public function test_id_checks_and_moderation_belong_to_cosel(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $ictd = $this->staff(Role::SuperAdmin);

        foreach (['manage_accounts', 'resolve_reports', 'takedown_listings', 'manage_farms'] as $permission) {
            $this->assertTrue($cosel->can($permission), $permission);
            $this->assertFalse($ictd->can($permission), $permission);
        }

        foreach (['manage_crop_types', 'set_crop_pricing', 'manage_admin_accounts'] as $permission) {
            $this->assertTrue($ictd->can($permission), $permission);
            $this->assertFalse($cosel->can($permission), $permission);
        }
    }

    public function test_bulk_creation_makes_farmer_sellers_and_skips_duplicates(): void
    {
        $farm = $this->farm();
        User::factory()->create(['email' => 'taken@example.com']);

        $result = app(BulkCreateFarmerSellers::class)->handle($farm, implode("\n", [
            'Name, Email, Mobile',
            'Juan Dela Cruz, juan@example.com, 09171234567',
            "Maria Santos\tmaria@example.com",
            'Taken Person, taken@example.com',
            'No Email',
        ]));

        $this->assertSame(['juan@example.com', 'maria@example.com'], $result['created']);
        $this->assertCount(2, $result['skipped']);

        $juan = User::query()->where('email', 'juan@example.com')->firstOrFail();
        $this->assertTrue($juan->isFarmerSeller());
        $this->assertSame($farm->id, $juan->farm_id);
        $this->assertTrue((bool) $juan->must_change_password);
    }

    // Content Editor and the farm's listings

    public function test_a_content_editor_edits_a_listing_on_their_farm_and_the_seller_is_told(): void
    {
        [$editor, $seller, $listing] = $this->farmListing();
        $this->actingAs($editor);

        app(EditFarmListing::class)->update($editor, $listing, $this->listingData($listing, ['price_per_unit' => 55]));

        $this->assertEquals(55, (float) $listing->fresh()->price_per_unit);
        $this->assertSame(1, InAppNotification::query()
            ->where('user_id', $seller->id)
            ->where('type', NotificationType::ListingEditedByFarm)
            ->count());
        $this->assertTrue(ActivityLog::query()
            ->where('user_id', $editor->id)
            ->where('subject_type', $listing->getMorphClass())
            ->where('subject_id', $listing->id)
            ->exists());
    }

    public function test_a_content_editor_cannot_price_below_the_floor(): void
    {
        [$editor, , $listing] = $this->farmListing(floor: 40);
        $this->actingAs($editor);

        try {
            app(EditFarmListing::class)->update($editor, $listing, $this->listingData($listing, ['price_per_unit' => 30]));
            $this->fail('A price below the floor was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('price_per_unit', $exception->errors());
        }

        $this->assertEquals(50, (float) $listing->fresh()->price_per_unit);
    }

    public function test_a_content_editor_cannot_edit_another_farms_listing(): void
    {
        [, , $listing] = $this->farmListing();
        $otherEditor = $this->staff(Role::ContentEditor, $this->farm());
        $this->actingAs($otherEditor);

        $this->expectException(AuthorizationException::class);

        app(EditFarmListing::class)->update($otherEditor, $listing, $this->listingData($listing, ['title' => 'Changed']));
    }

    public function test_a_content_editor_sets_a_tawad_within_the_limits(): void
    {
        [$editor, , $listing] = $this->farmListing(floor: 40, max: 5);
        $this->actingAs($editor);

        try {
            app(EditFarmListing::class)->setTawad($editor, $listing, ['type' => TawadType::Flat->value, 'discount_amount' => 8]);
            $this->fail('A tawad over the maximum was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discount_amount', $exception->errors());
        }

        app(EditFarmListing::class)->setTawad($editor, $listing, ['type' => TawadType::Flat->value, 'discount_amount' => 5]);

        $this->assertEquals(5, (float) $listing->fresh()->activeTawadRule->discount_amount);
    }

    public function test_a_seller_editing_in_the_app_is_not_in_the_activity_log(): void
    {
        [, $seller, $listing] = $this->farmListing();

        $this->asUser($seller)
            ->patchJson("/api/farmer/listings/{$listing->id}", ['title' => 'Fresh from the field'])
            ->assertOk();

        $this->assertSame(0, ActivityLog::query()->count());
    }

    // COSEL tools

    public function test_a_system_announcement_reaches_the_chosen_audience(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $buyer = $this->buyer();
        $farmer = $this->farmer();

        $sent = app(SendSystemAnnouncementAction::class)->handle($cosel, 'buyers', 'Market day', 'Saturday this week.');

        $this->assertSame(1, $sent);
        $this->assertSame(1, InAppNotification::query()->where('user_id', $buyer->id)->where('type', NotificationType::SystemAnnouncement)->count());
        $this->assertSame(0, InAppNotification::query()->where('user_id', $farmer->id)->count());
        $this->assertTrue(ActivityLog::query()->where('action', 'announced')->exists());
    }

    public function test_the_impact_report_has_one_row_per_farm(): void
    {
        $farmA = $this->farm();
        $this->farm();
        $this->farmer([], $farmA);

        $rows = app(ExportCommunityImpactAction::class)->rows(now()->subYear(), now());

        $this->assertCount(2, $rows);
        $row = collect($rows)->firstWhere('Farm', $farmA->name);
        $this->assertSame(1, $row['Active farmer-sellers']);
    }

    /**
     * @return array{0: User, 1: User, 2: Listing}
     */
    private function farmListing(float $floor = 40, ?float $max = 10): array
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $seller = $this->farmer([], $farm);
        $crop = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => $floor,
            'max_discount' => $max,
        ]);
        $listing = $this->listingFor($seller, [
            'crop_type_id' => $crop->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 50,
        ]);

        return [$editor, $seller, $listing];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function listingData(Listing $listing, array $changes): array
    {
        return array_merge([
            'title' => $listing->title,
            'unit' => $listing->unit?->value,
            'price_per_unit' => (float) $listing->price_per_unit,
            'quantity_available' => (float) $listing->quantity_available,
            'min_order_quantity' => (float) $listing->min_order_quantity,
            'order_step' => (float) $listing->order_step,
            'is_active' => (bool) $listing->is_active,
        ], $changes);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
