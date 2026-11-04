<?php

namespace Tests\Feature\Api;

use App\Enums\ListingUnit;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Http\Resources\Api\CropTypeResource;
use App\Models\CropType;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * A tawad is pesos off the whole order line. It is judged per unit: spread
 * over the quantity it applies to (the minimum quantity, or the listing's
 * minimum order for a flat tawad), converted into the crop type's unit, then
 * checked against the maximum tawad, when one is set, and the floor.
 */
class TawadPerUnitTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    // Bulk tawads: the seller's real case

    public function test_a_bulk_tawad_is_judged_per_bundle(): void
    {
        [$farmer, $listing] = $this->sitaw();

        // PHP 15 off 3 bundles is PHP 5 per bundle: within the PHP 5 maximum,
        // and PHP 20 - 5 = PHP 15 meets the floor.
        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 15, 3)->assertCreated();
    }

    public function test_a_bulk_tawad_that_breaks_the_floor_is_refused_per_bundle(): void
    {
        [$farmer, $listing] = $this->sitaw();

        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 18, 3)
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount_amount.0', 'This tawad would bring the price below the floor of PHP 15.00 per bundle.');
    }

    public function test_a_bulk_tawad_over_the_maximum_names_the_per_unit_amounts(): void
    {
        [$farmer, $listing] = $this->sitaw(price: 30);

        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 21, 3)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.discount_amount.0',
                'The maximum tawad for Sitaw is PHP 5.00 per bundle. This tawad works out to PHP 7.00 per bundle.',
            );
    }

    public function test_checkout_applies_a_bulk_tawad_on_the_whole_line(): void
    {
        [$farmer, $listing] = $this->sitaw();
        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 15, 3)->assertCreated();
        $buyer = $this->buyer();

        $this->addToCart($buyer, $listing, 3);
        $order = $this->checkout($buyer)->assertCreated()->json('data.0');

        $this->assertEquals(15, $order['tawad_total']);
        $this->assertEquals(45, $order['total']);
    }

    // Piece and dozen

    public function test_a_piece_listing_on_a_dozen_guarded_crop_takes_a_bulk_tawad(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Dozen, floor: 90, max: 12);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Piece, price: 10);

        // PHP 5 off 12 pieces is PHP 5 per dozen: within PHP 12 per dozen.
        // It used to be multiplied by 12 and refused as PHP 60.
        $this->tawad($farmer, $listing, TawadType::MinimumQuantity, 5, 12)->assertCreated();
    }

    public function test_a_flat_tawad_on_a_piece_listing_is_converted_to_the_dozen_limit(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Dozen, floor: 90, max: 12);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Piece, price: 10);

        // PHP 1.50 off one piece is PHP 18 per dozen, over PHP 12.
        $this->tawad($farmer, $listing, TawadType::Flat, 1.5)->assertUnprocessable();
        $this->tawad($farmer, $listing, TawadType::Flat, 1)->assertCreated();
    }

    public function test_a_dozen_listing_on_a_piece_guarded_crop_is_held_to_the_piece_limit(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Piece, floor: 8, max: 2);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Dozen, price: 110);

        // PHP 30 off a dozen is PHP 2.50 per piece, over PHP 2.
        $this->tawad($farmer, $listing, TawadType::Flat, 30)->assertUnprocessable();
        // PHP 12 off a dozen is PHP 1 per piece, and PHP 98 a dozen is PHP 8.17 a piece.
        $this->tawad($farmer, $listing, TawadType::Flat, 12)->assertCreated();
    }

    public function test_checkout_charges_what_the_dozen_tawad_allows(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Piece, floor: 8, max: 2);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Dozen, price: 110);
        $this->tawad($farmer, $listing, TawadType::Flat, 12)->assertCreated();
        $buyer = $this->buyer();

        $this->addToCart($buyer, $listing, 1);
        $order = $this->checkout($buyer)->assertCreated()->json('data.0');

        $this->assertEquals(12, $order['tawad_total']);
        $this->assertEquals(98, $order['total']);
    }

    // Flat tawads judged on the minimum order

    public function test_a_flat_tawad_on_a_per_gram_listing_is_spread_over_its_minimum_order(): void
    {
        $crop = $this->crop('Kalabasa', ListingUnit::Kilogram, floor: 40, max: 5);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Gram, price: 0.05, attributes: [
            'min_order_quantity' => 500,
            'order_step' => 1,
        ]);

        // PHP 2 off 500 g is PHP 4 per kg. It used to be judged on one gram,
        // PHP 2,000 per kg, and always refused.
        $this->tawad($farmer, $listing, TawadType::Flat, 2)->assertCreated();
    }

    // An optional maximum tawad

    public function test_with_no_maximum_the_floor_alone_limits_a_tawad(): void
    {
        $crop = $this->crop('Pechay', ListingUnit::Kilogram, floor: 40, max: null);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Kilogram, price: 60);

        $this->tawad($farmer, $listing, TawadType::Flat, 15)->assertCreated();
        $listing->tawadRules()->delete();
        $this->tawad($farmer, $listing, TawadType::Flat, 25)
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount_amount.0', 'This tawad would bring the price below the floor of PHP 40.00 per kg.');
    }

    public function test_a_maximum_of_zero_turns_tawad_off(): void
    {
        $crop = $this->crop('Okra', ListingUnit::Kilogram, floor: 40, max: 0);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Kilogram, price: 60);

        $this->tawad($farmer, $listing, TawadType::Flat, 1)
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount_amount.0', 'Tawad is turned off for Okra.');
    }

    public function test_the_api_sends_a_blank_maximum_as_null(): void
    {
        $crop = $this->crop('Pechay', ListingUnit::Kilogram, floor: 40, max: null);

        $data = (new CropTypeResource($crop))->toArray(request());

        $this->assertNull($data['max_discount']);
        $this->assertNull($data['effective_max_discount']);
    }

    // The limits in the listing's own unit, for the app

    public function test_the_seller_gets_the_limits_in_the_listings_own_unit(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Dozen, floor: 90, max: 12);
        [$farmer, $listing] = $this->listingOf($crop, ListingUnit::Piece, price: 10);

        $guard = $this->asUser($farmer)
            ->getJson("/api/farmer/listings/{$listing->id}")
            ->assertOk()
            ->json('data.price_guard');

        $this->assertSame('piece', $guard['unit']);
        $this->assertEquals(7.5, $guard['floor_per_unit']);
        $this->assertEquals(1, $guard['max_tawad_per_unit']);
        $this->assertTrue($guard['unit_allowed']);
    }

    public function test_a_buyer_does_not_see_the_price_guard(): void
    {
        $crop = $this->crop('Itlog', ListingUnit::Dozen, floor: 90, max: 12);
        [, $listing] = $this->listingOf($crop, ListingUnit::Piece, price: 10);

        $this->asUser($this->buyer())
            ->getJson("/api/buyer/marketplace/{$listing->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.price_guard');
    }

    // Buyers made in the CMS

    public function test_a_buyer_created_in_the_cms_must_verify_an_id(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->syncRoles(Role::SuperAdmin);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Walk-up Buyer',
                'email' => 'cms.buyer@example.com',
                'roles' => (int) RoleModel::findByName(Role::Buyer->value)->id,
                'status' => UserStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $buyer = User::query()->where('email', 'cms.buyer@example.com')->firstOrFail();

        $this->assertTrue((bool) $buyer->requires_id_verification);
    }

    /**
     * Sitaw, guarded per bundle: floor PHP 15, maximum tawad PHP 5 per bundle.
     *
     * @return array{0: User, 1: Listing}
     */
    private function sitaw(float $price = 20): array
    {
        return $this->listingOf($this->crop('Sitaw', ListingUnit::Bundle, floor: 15, max: 5), ListingUnit::Bundle, $price);
    }

    private function crop(string $name, ListingUnit $unit, float $floor, ?float $max): CropType
    {
        return $this->cropType([
            'name' => $name,
            'unit_of_measure' => $unit,
            'floor_price' => $floor,
            'max_discount' => $max,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: User, 1: Listing}
     */
    private function listingOf(CropType $crop, ListingUnit $unit, float $price, array $attributes = []): array
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, [
            'crop_type_id' => $crop->id,
            'unit' => $unit,
            'price_per_unit' => $price,
            ...$attributes,
        ]);

        return [$farmer, $listing];
    }

    private function tawad(User $farmer, Listing $listing, TawadType $type, float $amount, ?float $minQuantity = null): TestResponse
    {
        return $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/tawad", array_filter([
            'type' => $type->value,
            'discount_amount' => $amount,
            'min_quantity' => $minQuantity,
        ], fn (mixed $value): bool => $value !== null));
    }
}
