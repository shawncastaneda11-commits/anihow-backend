<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class MarketplaceFairMixTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_each_seller_appears_once_before_a_second_listing_from_the_first_seller(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

        $sellerA = $this->farmer();
        $sellerB = $this->farmer();
        $sellerC = $this->farmer();

        foreach (range(1, 5) as $index) {
            $this->listingFor($sellerA, [
                'title' => 'A '.$index,
                'created_at' => now()->subMinutes(40 - $index),
            ]);
        }

        $this->listingFor($sellerB, [
            'title' => 'B 1',
            'created_at' => now()->subMinutes(8),
        ]);
        $this->listingFor($sellerC, [
            'title' => 'C 1',
            'created_at' => now()->subMinutes(2),
        ]);

        $sellerIds = collect($this->marketplace()->json('data'))->pluck('seller.id');

        $this->assertEqualsCanonicalizing(
            [$sellerA->id, $sellerB->id, $sellerC->id],
            $sellerIds->take(3)->all(),
        );
        $this->assertSame(4, $sellerIds->skip(3)->count());
        $sellerIds->skip(3)->each(fn ($id) => $this->assertSame($sellerA->id, $id));
    }

    public function test_available_listings_come_before_upcoming_ones(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

        $seller = $this->farmer();
        $upcoming = $this->listingFor($seller, [
            'title' => 'Later harvest',
            'available_from' => now()->addDays(3),
            'created_at' => now()->subMinute(),
        ]);
        $available = $this->listingFor($seller, [
            'title' => 'Ready now',
            'available_from' => now()->subDay(),
            'created_at' => now()->subHour(),
        ]);

        $ids = collect($this->marketplace()->json('data'))->pluck('id');

        $this->assertTrue($ids->search($available->id) < $ids->search($upcoming->id));
    }

    public function test_the_order_is_stable_within_a_day_and_rotates_across_days(): void
    {
        $sellers = collect(range(1, 5))->map(fn (): User => $this->farmer());
        $buyer = $this->buyer();

        foreach ($sellers as $seller) {
            $this->listingFor($seller, ['title' => 'Listing '.$seller->id]);
        }

        $this->travelTo(Carbon::parse('2026-10-06 09:00:00', 'Asia/Manila'));

        $first = collect($this->asUser($buyer)->getJson('/api/buyer/marketplace')->json('data'))->pluck('id')->all();
        $second = collect($this->asUser($buyer)->getJson('/api/buyer/marketplace')->json('data'))->pluck('id')->all();

        $this->assertSame($first, $second);

        $leaders = [];

        foreach (range(0, 6) as $offset) {
            $this->travelTo(Carbon::parse('2026-10-06 09:00:00', 'Asia/Manila')->addDays($offset));
            $leaders[] = $this->asUser($buyer)->getJson('/api/buyer/marketplace')->json('data.0.seller.id');
        }

        $this->assertGreaterThan(1, collect($leaders)->unique()->count());
    }

    public function test_pages_do_not_repeat_or_skip_and_mix_day_holds_the_order(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

        $sellers = collect(range(1, 3))->map(fn (): User => $this->farmer());
        $expected = [];

        foreach ($sellers as $seller) {
            foreach (range(1, 2) as $index) {
                $expected[] = $this->listingFor($seller, [
                    'title' => $seller->id.' '.$index,
                    'created_at' => now()->subMinutes($index),
                ])->id;
            }
        }

        $buyer = $this->buyer();
        $pageOne = $this->asUser($buyer)->getJson('/api/buyer/marketplace?per_page=2')->assertOk();
        $mixDay = $pageOne->json('meta.mix_day');
        $pageTwo = $this->asUser($buyer)->getJson('/api/buyer/marketplace?per_page=2&page=2&mix_day='.$mixDay)->assertOk();
        $pageThree = $this->asUser($buyer)->getJson('/api/buyer/marketplace?per_page=2&page=3&mix_day='.$mixDay)->assertOk();

        $seen = [
            ...collect($pageOne->json('data'))->pluck('id'),
            ...collect($pageTwo->json('data'))->pluck('id'),
            ...collect($pageThree->json('data'))->pluck('id'),
        ];

        $this->assertCount(6, $seen);
        $this->assertEqualsCanonicalizing($expected, $seen);
        $this->assertSame('2026-10-06', $mixDay);

        $this->travelTo(Carbon::parse('2026-10-07 10:00:00', 'Asia/Manila'));

        $held = [
            ...collect($this->asUser($buyer)->getJson('/api/buyer/marketplace?per_page=2&mix_day='.$mixDay)->json('data'))->pluck('id'),
            ...collect($this->asUser($buyer)->getJson('/api/buyer/marketplace?per_page=2&page=2&mix_day='.$mixDay)->json('data'))->pluck('id'),
            ...collect($this->asUser($buyer)->getJson('/api/buyer/marketplace?per_page=2&page=3&mix_day='.$mixDay)->json('data'))->pluck('id'),
        ];

        $this->assertSame($seen, $held);
    }

    public function test_other_sorts_and_filters_still_work_and_a_bad_mix_day_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

        $olderSeller = $this->farmer();
        $newerSeller = $this->farmer();
        $olderSeller->farm->update(['municipality' => 'Cavite']);
        $newerSeller->farm->update(['municipality' => 'Albay']);

        $this->listingFor($olderSeller, [
            'title' => 'Older cheap scarce',
            'price_per_unit' => 10,
            'quantity_available' => 1,
            'created_at' => now()->subDay(),
        ]);
        $newest = $this->listingFor($newerSeller, [
            'title' => 'Newest costly plentiful',
            'price_per_unit' => 80,
            'quantity_available' => 20,
            'created_at' => now()->subMinute(),
        ]);

        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?sort=freshest')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonMissingPath('meta.mix_day');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Older cheap scarce');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?sort=availability')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Newest costly plentiful');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?sort=nearest')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Newest costly plentiful')
            ->assertJsonPath('data.1.title', 'Older cheap scarce');

        $filtered = $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?search=scarce')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Older cheap scarce');

        $this->assertSame('2026-10-06', $filtered->json('meta.mix_day'));

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?mix_day=2020-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mix_day');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?mix_day=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mix_day');
    }

    private function marketplace(): TestResponse
    {
        return $this->asUser($this->buyer())->getJson('/api/buyer/marketplace')->assertOk();
    }
}
