<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\CartItem;
use App\Models\Listing;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ListingAvailabilityTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_available_now_includes_the_start_instant_and_excludes_the_end_instant(): void
    {
        $farmer = $this->farmer();
        $start = Carbon::parse('2026-10-20 08:00:00');
        $end = Carbon::parse('2026-10-20 12:00:00');
        $open = $this->listingFor($farmer, ['title' => 'Open']);
        $window = $this->listingFor($farmer, [
            'title' => 'Window',
            'available_from' => $start,
            'available_until' => $end,
        ]);

        $this->travelTo($start);

        $this->assertTrue($this->onSale($open));
        $this->assertTrue($this->onSale($window));
        $this->assertFalse($window->fresh()->isUpcoming());
        $this->assertFalse($window->fresh()->isExpired());
        $this->assertSame('available', $window->fresh()->availabilityState());

        $this->travelTo($start->copy()->subSecond());

        $this->assertFalse($this->onSale($window));
        $this->assertTrue($window->fresh()->isUpcoming());
        $this->assertSame('upcoming', $window->fresh()->availabilityState());

        $this->travelTo($end);

        $this->assertFalse($this->onSale($window));
        $this->assertTrue($window->fresh()->isExpired());
        $this->assertFalse($window->fresh()->isUpcoming());
        $this->assertSame('expired', $window->fresh()->availabilityState());
        $this->assertTrue($this->onSale($open));
    }

    public function test_an_expired_listing_leaves_browse_and_stays_on_the_seller_list(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Morning crate',
            'available_until' => Carbon::parse('2026-10-03 18:00:00'),
        ]);

        $this->travelTo(Carbon::parse('2026-10-03 18:00:00'));

        $browse = collect(
            $this->asUser($buyer)->getJson('/api/buyer/marketplace')->json('data'),
        )->pluck('id');

        $this->assertFalse($browse->contains($listing->id));
        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertNotFound();

        $this->asUser($farmer)
            ->getJson('/api/farmer/listings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $listing->id)
            ->assertJsonPath('data.0.availability_state', 'expired');
    }

    public function test_an_upcoming_listing_is_shown_and_refused_by_the_cart(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Next harvest',
            'available_from' => Carbon::parse('2026-10-20 08:00:00'),
        ]);

        $this->travelTo(Carbon::parse('2026-10-10 08:00:00'));

        $shown = collect(
            $this->asUser($buyer)->getJson('/api/buyer/marketplace')->json('data'),
        )->firstWhere('id', $listing->id);

        $this->assertNotNull($shown);
        $this->assertTrue($shown['is_upcoming']);

        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('listing_id');
    }

    public function test_checkout_refuses_a_line_that_expired_after_it_was_added(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Same-day pechay',
            'available_until' => Carbon::parse('2026-10-04 17:00:00'),
            'quantity_available' => 10,
        ]);

        $this->travelTo(Carbon::parse('2026-10-04 09:00:00'));
        $this->addToCart($buyer, $listing, 1);

        $this->travelTo(Carbon::parse('2026-10-04 17:00:00'));

        $this->checkout($buyer)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');
    }

    public function test_checkout_refuses_a_line_that_became_upcoming_and_keeps_it(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Next week okra',
            'quantity_available' => 10,
        ]);

        $this->addToCart($buyer, $listing, 1);
        $listing->update(['available_from' => now()->addDays(4)]);

        $this->checkout($buyer)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cart' => 'Next week okra is not available yet. Remove it from your cart to check out.',
            ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', [
            'buyer_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_a_taken_down_expired_listing_is_hidden_from_the_cart(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Hidden crate',
            'status' => ListingStatus::TakenDown,
            'available_until' => now()->subHour(),
        ]);

        $response = $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => 1,
            ])
            ->assertNotFound();

        $this->assertStringNotContainsString('Hidden crate', $response->getContent());
    }

    public function test_updating_an_expired_line_deletes_it(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, [
            'title' => 'Morning sitaw',
            'available_until' => Carbon::parse('2026-10-04 17:00:00'),
            'quantity_available' => 10,
        ]);

        $this->travelTo(Carbon::parse('2026-10-04 09:00:00'));
        $this->addToCart($buyer, $listing, 1);

        $item = CartItem::query()
            ->where('buyer_id', $buyer->id)
            ->where('listing_id', $listing->id)
            ->firstOrFail();

        $this->travelTo(Carbon::parse('2026-10-04 17:00:00'));

        $this->asUser($buyer)
            ->patchJson('/api/buyer/cart/'.$item->id, ['quantity' => 2])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_listing_dates_are_validated(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType([
            'floor_price' => 20,
            'max_discount' => 5,
        ]);
        $body = [
            'title' => 'Kamatis',
            'crop_type_id' => $cropType->id,
            'unit' => 'kg',
            'price_per_unit' => 30,
            'quantity_available' => 8,
        ];

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                ...$body,
                'available_from' => '2026-10-20 08:00:00',
                'available_until' => '2026-10-20 08:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('available_until');

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                ...$body,
                'harvested_on' => now()->addDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('harvested_on');

        $this->asUser($farmer)
            ->postJson('/api/farmer/listings', [
                ...$body,
                'title' => 'Later kamatis',
                'available_from' => now()->addDays(3)->toDateTimeString(),
                'harvested_on' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_upcoming', true)
            ->assertJsonPath('data.availability_state', 'upcoming')
            ->assertJsonPath('data.harvested_on', now()->toDateString());
    }

    private function onSale(Listing $listing): bool
    {
        return Listing::query()->availableNow()->whereKey($listing->id)->exists();
    }
}
