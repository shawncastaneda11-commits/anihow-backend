<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use App\Support\GeoDistance;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmProximityTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_farm_coordinates_must_be_a_philippine_pair(): void
    {
        $farm = Farm::factory()->create();

        try {
            $farm->update(['latitude' => 14.2]);
            $this->fail('A single coordinate was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('longitude', $exception->errors());
        }

        $farm->refresh();
        $this->assertNull($farm->latitude);

        try {
            $farm->update(['latitude' => 30, 'longitude' => 120.9]);
            $this->fail('An out-of-range pin was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('latitude', $exception->errors());
        }

        try {
            $farm->update(['latitude' => 14.2, 'longitude' => 100]);
            $this->fail('An out-of-range pin was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('longitude', $exception->errors());
        }

        $farm->update(['latitude' => 14.2, 'longitude' => 120.9]);
        $farm->refresh();
        $this->assertEquals(14.2, (float) $farm->latitude);
        $this->assertEquals(120.9, (float) $farm->longitude);

        $farm->update(['latitude' => null, 'longitude' => null]);
        $this->assertNull($farm->refresh()->latitude);
    }

    public function test_near_query_rejects_a_partial_or_out_of_range_point(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?near_lat=14.2')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('near_lng');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?near_lng=120.9')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('near_lat');

        $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?near_lat=30&near_lng=120.9')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('near_lat');

        $this->asUser($buyer)
            ->getJson('/api/buyer/shops?sort=nearest&near_lat=14.2&near_lng=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('near_lng');
    }

    public function test_nearest_puts_the_closest_farm_first_and_unpinned_farms_last(): void
    {
        $close = $this->pinnedFarmer('Close Stall', 'Close Farm', 'Cavite', 14.21, 120.91);
        $north = $this->pinnedFarmer('North Stall', 'North Farm', 'Laguna', 14.8, 121.2);
        $far = $this->pinnedFarmer('Far Stall', 'Far Farm', 'Albay', 13.2, 123.7);
        $unpinned = $this->farmer(['shop_name' => 'No Pin Stall'], Farm::factory()->create([
            'name' => 'No Pin Farm',
            'municipality' => 'Batangas',
        ]));

        $this->listingFor($close, ['title' => 'Close kamatis']);
        $this->listingFor($north, ['title' => 'North kamatis']);
        $this->listingFor($far, ['title' => 'Far kamatis']);
        $this->listingFor($unpinned, ['title' => 'No pin kamatis']);

        $buyer = $this->buyer();
        $query = 'sort=nearest&near_lat=14.20&near_lng=120.90';

        $listings = $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?'.$query)
            ->assertOk();

        $this->assertSame(
            ['Close kamatis', 'North kamatis', 'Far kamatis', 'No pin kamatis'],
            collect($listings->json('data'))->pluck('title')->all(),
        );
        $this->assertNotNull($listings->json('data.0.distance_km'));
        $this->assertNotNull($listings->json('data.0.farm.distance_km'));
        $this->assertNull($listings->json('data.3.distance_km'));
        $this->assertLessThan(
            $listings->json('data.1.distance_km'),
            $listings->json('data.0.distance_km'),
        );

        $shops = $this->asUser($buyer)
            ->getJson('/api/buyer/shops?'.$query)
            ->assertOk();

        $this->assertSame(
            ['Close Stall', 'North Stall', 'Far Stall', 'No Pin Stall'],
            collect($shops->json('data'))->pluck('shop_name')->all(),
        );
        $this->assertNotNull($shops->json('data.0.distance_km'));
        $this->assertNull($shops->json('data.3.distance_km'));

        $expected = GeoDistance::kilometers(14.21, 120.91, Request::create('/api/buyer/marketplace', 'GET', [
            'near_lat' => '14.20',
            'near_lng' => '120.90',
        ]));
        $this->assertEquals($expected, $listings->json('data.0.distance_km'));
    }

    public function test_distance_is_omitted_without_a_buyer_point_and_nearest_falls_back_to_municipality(): void
    {
        $cavite = $this->pinnedFarmer('Cavite Stall', 'Cavite Farm', 'Cavite', null, null);
        $albay = $this->pinnedFarmer('Albay Stall', 'Albay Farm', 'Albay', null, null);
        $batangas = $this->pinnedFarmer('Batangas Stall', 'Batangas Farm', 'Batangas', null, null);
        $this->listingFor($cavite, ['title' => 'Cavite kamatis']);
        $this->listingFor($albay, ['title' => 'Albay kamatis']);
        $this->listingFor($batangas, ['title' => 'Batangas kamatis']);

        $buyer = $this->buyer();

        $plain = $this->asUser($buyer)->getJson('/api/buyer/marketplace')->assertOk();
        $plain->assertJsonMissingPath('data.0.distance_km');
        $plain->assertJsonMissingPath('data.0.farm.distance_km');

        $listings = $this->asUser($buyer)
            ->getJson('/api/buyer/marketplace?sort=nearest')
            ->assertOk();
        $this->assertSame(
            ['Albay kamatis', 'Batangas kamatis', 'Cavite kamatis'],
            collect($listings->json('data'))->pluck('title')->all(),
        );
        $listings->assertJsonMissingPath('data.0.distance_km');

        $shops = $this->asUser($buyer)
            ->getJson('/api/buyer/shops?sort=nearest')
            ->assertOk();
        $this->assertSame(
            ['Albay Stall', 'Batangas Stall', 'Cavite Stall'],
            collect($shops->json('data'))->pluck('shop_name')->all(),
        );
        $shops->assertJsonMissingPath('data.0.distance_km');
    }

    public function test_a_near_me_request_does_not_store_the_buyer_point(): void
    {
        $buyer = $this->buyer(['location' => null]);
        $before = User::query()->count();
        $secret = '14.8765432';

        $response = $this->asUser($buyer)->getJson(
            '/api/buyer/marketplace?sort=nearest&near_lat='.$secret.'&near_lng=120.9',
        );
        $response->assertOk();
        $this->assertStringNotContainsString($secret, $response->getContent());

        $this->assertSame($before, User::query()->count());
        $this->assertNull($buyer->refresh()->location);
        $this->assertSame(0, User::query()->where('location', 'like', '%'.$secret.'%')->count());
        $this->assertSame(0, Farm::query()->where('latitude', $secret)->count());
    }

    private function pinnedFarmer(
        string $shopName,
        string $farmName,
        string $municipality,
        ?float $latitude,
        ?float $longitude,
    ): User {
        $farm = Farm::factory()->create([
            'name' => $farmName,
            'municipality' => $municipality,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        return $this->farmer(['shop_name' => $shopName], $farm);
    }
}
