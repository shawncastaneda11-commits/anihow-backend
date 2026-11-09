<?php

namespace Tests\Feature;

use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Enums\ListingUnit;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Enums\UserStatus;
use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Filament\Resources\Farms\RelationManagers\FarmListingsRelationManager;
use App\Filament\Resources\Farms\RelationManagers\FarmSellersRelationManager;
use App\Filament\Resources\IdVerifications\Pages\ListIdVerifications;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Farm;
use App\Models\IdVerification;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * The redesigned work screens: a Content Editor's listing panel, COSEL's ID
 * check review, the account panel and the farm's partnership record.
 */
class CmsWorkScreensTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_listing_panel_saves_price_and_tawad_and_tells_the_seller(): void
    {
        [$editor, $seller, $listing] = $this->farmListing();

        $page = Livewire::actingAs($editor)->test(ListListings::class);

        // A row opens the panel; it is not a link, and there is no row menu.
        $this->assertNull($page->instance()->getTable()->getRecordUrl($listing));
        $page->assertTableActionHidden('view', $listing);

        $page->call('openListing', $listing->id)
            ->assertSet('openId', $listing->id)
            ->set('listing.price_per_unit', 55)
            ->set('listing.type', TawadType::Flat->value)
            ->set('listing.discount_amount', 5)
            ->call('saveListing')
            ->assertHasNoErrors();

        $listing->refresh();
        $this->assertEquals(55, (float) $listing->price_per_unit);
        $this->assertEquals(5, (float) $listing->activeTawadRule->discount_amount);
        $this->assertGreaterThan(0, InAppNotification::query()
            ->where('user_id', $seller->id)
            ->where('type', NotificationType::ListingEditedByFarm)
            ->count());
    }

    public function test_the_listing_panel_refuses_a_price_below_the_floor_and_keeps_the_tawad(): void
    {
        [$editor, , $listing] = $this->farmListing(floor: 40);

        Livewire::actingAs($editor)
            ->test(ListListings::class)
            ->call('openListing', $listing->id)
            ->set('listing.price_per_unit', 30)
            ->set('listing.type', TawadType::Flat->value)
            ->set('listing.discount_amount', 5)
            ->call('saveListing')
            ->assertHasErrors(['listing.price_per_unit']);

        $listing->refresh();
        $this->assertEquals(50, (float) $listing->price_per_unit);
        $this->assertNull($listing->activeTawadRule);
    }

    public function test_choosing_no_tawad_ends_the_current_one(): void
    {
        [$editor, , $listing] = $this->farmListing();
        $listing->tawadRules()->create(['type' => TawadType::Flat, 'discount_amount' => 3, 'is_active' => true]);

        Livewire::actingAs($editor)
            ->test(ListListings::class)
            ->call('openListing', $listing->id)
            ->set('listing.type', 'none')
            ->call('saveListing')
            ->assertHasNoErrors();

        $this->assertNull($listing->fresh()->activeTawadRule);
    }

    public function test_the_below_the_lowest_price_tab_shows_only_those_listings(): void
    {
        [$editor, $seller, $fine] = $this->farmListing(floor: 40);
        $low = $this->listingFor($seller, [
            'crop_type_id' => $fine->crop_type_id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 50,
        ]);
        $low->forceFill(['price_per_unit' => 20])->saveQuietly();

        Livewire::actingAs($editor)
            ->test(ListListings::class)
            ->set('activeTab', 'below')
            ->assertCanSeeTableRecords([$low])
            ->assertCanNotSeeTableRecords([$fine]);
    }

    public function test_a_crop_in_another_unit_does_not_put_every_listing_below_the_lowest_price(): void
    {
        [$editor, , $fine] = $this->farmListing(floor: 40);
        $this->cropType(['unit_of_measure' => ListingUnit::Piece, 'floor_price' => 10]);

        Livewire::actingAs($editor)
            ->test(ListListings::class)
            ->set('activeTab', 'below')
            ->assertCanNotSeeTableRecords([$fine]);
    }

    public function test_the_tawad_tab_warns_as_you_type_and_an_untouched_panel_changes_nothing(): void
    {
        [$editor, , $listing] = $this->farmListing();
        $listing->tawadRules()->create(['type' => TawadType::MinimumQuantity, 'discount_amount' => 5, 'min_quantity' => 3, 'is_active' => true]);

        Livewire::actingAs($editor)
            ->test(ListListings::class)
            ->call('openListing', $listing->id)
            ->assertSee('Nothing changed yet')
            ->set('listing.type', TawadType::Flat->value)
            ->set('listing.discount_amount', 100)
            ->assertSee('Too big')
            ->assertSee('will be told: new tawad');
    }

    public function test_the_seller_choice_and_search_narrow_the_listings(): void
    {
        [$editor, $seller, $listing] = $this->farmListing();
        $other = $this->farmer([], $listing->farm);
        $theirs = $this->listingFor($other, [
            'crop_type_id' => $listing->crop_type_id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 50,
            'title' => 'Kalabasa ni Pedro',
        ]);

        Livewire::actingAs($editor)
            ->test(ListListings::class)
            ->set('owner', (string) $seller->id)
            ->assertCanSeeTableRecords([$listing])
            ->assertCanNotSeeTableRecords([$theirs])
            ->set('owner', '')
            ->set('search', 'Kalabasa ni')
            ->assertCanSeeTableRecords([$theirs])
            ->assertCanNotSeeTableRecords([$listing]);
    }

    public function test_cosel_works_through_the_id_queue_oldest_first(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $older = $this->pendingId(now()->subDay());
        $newer = $this->pendingId(now());

        Livewire::actingAs($cosel)
            ->test(ListIdVerifications::class)
            ->assertSet('selectedId', $older->id)
            ->call('approve')
            ->assertSet('selectedId', $newer->id)
            ->call('startReject')
            ->set('reason', 'expired')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertSet('selectedId', null);

        $this->assertSame(IdVerificationStatus::Approved, $older->fresh()->status);
        $this->assertSame(IdVerificationStatus::Rejected, $newer->fresh()->status);
    }

    private function pendingId(\DateTimeInterface $sentAt): IdVerification
    {
        $verification = IdVerification::query()->create([
            'user_id' => $this->buyer()->id,
            'id_type' => IdDocumentType::NationalId,
            'status' => IdVerificationStatus::Pending,
            'consented_at' => now(),
        ]);
        $verification->forceFill(['created_at' => $sentAt])->saveQuietly();

        return $verification;
    }

    public function test_the_account_panel_signs_a_person_out_everywhere(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $buyer = $this->buyer();
        $buyer->createToken('phone');

        $page = Livewire::actingAs($cosel)->test(ListUsers::class);

        // A row opens the panel; it is not a link to the full page.
        $this->assertNull($page->instance()->getTable()->getRecordUrl($buyer));

        $page->call('openAccount', $buyer->id)
            ->assertSet('openId', $buyer->id)
            ->callAction('signOutEverywhere');

        $this->assertSame(0, $buyer->tokens()->count());
    }

    public function test_the_account_panel_saves_the_mobile_number_and_suspends(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $buyer = $this->buyer(['phone' => '09170000000']);

        Livewire::actingAs($cosel)
            ->test(ListUsers::class)
            ->call('openAccount', $buyer->id)
            ->set('account.phone', '09171112222')
            ->call('saveAccount')
            ->assertHasNoErrors()
            ->callAction('suspendOpenAccount', data: ['suspension_reason' => 'Asked to pause.'])
            ->assertHasNoActionErrors();

        $buyer->refresh();
        $this->assertSame('09171112222', $buyer->phone);
        $this->assertSame(UserStatus::Suspended, $buyer->status);
        $this->assertTrue($buyer->hasRole(Role::Buyer->value));
    }

    public function test_a_content_editor_sees_the_account_panel_read_only(): void
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $seller = $this->farmer([], $farm);

        Livewire::actingAs($editor)
            ->test(ListUsers::class)
            ->call('openAccount', $seller->id)
            ->assertSet('openId', $seller->id)
            ->assertActionHidden('signOutEverywhere')
            ->call('saveAccount')
            ->assertForbidden();
    }

    public function test_cosel_records_the_partnership_and_a_content_editor_cannot(): void
    {
        $farm = $this->farm();

        Livewire::actingAs($this->staff(Role::AccountManager))
            ->test(EditFarm::class, ['record' => $farm->id])
            ->callAction('editPartnership', data: [
                'partnered_since' => '2025-06-01',
                'partnership_notes' => 'Signed the agreement in June.',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('2025-06-01', $farm->fresh()->partnered_since?->toDateString());

        Livewire::actingAs($this->staff(Role::ContentEditor, $farm))
            ->test(EditFarm::class, ['record' => $farm->id])
            ->assertActionHidden('editPartnership');
    }

    public function test_the_farm_page_has_sellers_and_listings_tabs(): void
    {
        [$editor, $seller, $listing] = $this->farmListing();
        $farm = $listing->farm;
        $elsewhere = $this->farmer();

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->assertSee('Sellers')
            ->assertSee('Listings')
            ->assertSee('Price guards');

        Livewire::actingAs($editor)
            ->test(FarmSellersRelationManager::class, ['ownerRecord' => $farm, 'pageClass' => EditFarm::class])
            ->assertCanSeeTableRecords([$seller])
            ->assertCanNotSeeTableRecords([$elsewhere]);

        Livewire::actingAs($editor)
            ->test(FarmListingsRelationManager::class, ['ownerRecord' => $farm, 'pageClass' => EditFarm::class])
            ->assertCanSeeTableRecords([$listing]);
    }

    /**
     * @return array{0: User, 1: User, 2: Listing}
     */
    private function farmListing(float $floor = 40): array
    {
        $farm = $this->farm();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $seller = $this->farmer([], $farm);
        $crop = $this->cropType([
            'unit_of_measure' => ListingUnit::Kilogram,
            'floor_price' => $floor,
            'max_discount' => 10,
        ]);
        $listing = $this->listingFor($seller, [
            'crop_type_id' => $crop->id,
            'unit' => ListingUnit::Kilogram,
            'price_per_unit' => 50,
            'min_order_quantity' => 1,
            'order_step' => 1,
        ]);

        return [$editor, $seller, $listing];
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
