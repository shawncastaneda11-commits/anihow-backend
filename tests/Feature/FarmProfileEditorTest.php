<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Farms\FarmResource;
use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Filament\Resources\Farms\Pages\ListFarms;
use App\Models\Farm;
use App\Models\User;
use App\Support\GoogleMapsLinkResolver;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use App\Support\NominatimPlaceSearch;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmProfileEditorTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_content_editor_opens_their_own_farm_and_cannot_open_another(): void
    {
        $own = Farm::factory()->create(['name' => 'Own Farm']);
        $other = Farm::factory()->create(['name' => 'Other Farm']);
        $editor = $this->staff(Role::ContentEditor, $own);
        $unassigned = $this->staff(Role::ContentEditor);

        $this->actingAs($editor);
        $items = FarmResource::getNavigationItems();

        $this->assertCount(1, $items);
        $this->assertSame('My Farm', $items[0]->getLabel());
        $this->assertSame(
            FarmResource::getUrl('edit', ['record' => $own->id]),
            $items[0]->getUrl(),
        );

        Livewire::actingAs($editor)
            ->test(ListFarms::class)
            ->assertRedirect(FarmResource::getUrl('edit', ['record' => $own->id]));

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $own->id])
            ->assertSee('Own Farm')
            ->assertSee('Edit cover')
            ->assertSee('About')
            ->assertSee('Edit contact')
            ->assertSee('Photos')
            ->assertSee('Price guards')
            ->assertSee('Crop types')
            ->assertActionHidden('editOrganic')
            ->assertActionHidden('editStatus');

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $other->id])
            ->assertForbidden();

        $this->actingAs($unassigned);
        $unassignedItems = FarmResource::getNavigationItems();
        $this->assertSame('My Farm', $unassignedItems[0]->getLabel());
        $this->assertSame(FarmResource::getUrl('index'), $unassignedItems[0]->getUrl());

        Livewire::actingAs($unassigned)
            ->test(ListFarms::class)
            ->assertSee('Your account is not assigned to a farm yet.')
            ->assertDontSee('Other Farm');
    }

    public function test_a_super_admin_keeps_the_farm_list_and_edits_any_farm(): void
    {
        $farm = Farm::factory()->create([
            'name' => 'Listed Farm',
            'value_added_enabled' => true,
            'reservations_enabled' => true,
            'tawad_enabled' => true,
            'walk_in_enabled' => true,
        ]);
        $admin = $this->staff(Role::SuperAdmin);

        $this->actingAs($admin);
        $this->assertSame('Farms', FarmResource::getNavigationItems()[0]->getLabel());

        Livewire::actingAs($admin)
            ->test(ListFarms::class)
            ->assertSee('Listed Farm');

        Livewire::actingAs($admin)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->callAction('editAbout', data: [
                'description' => 'Admin description',
            ])
            ->callAction('editStatus', data: [
                'is_active' => false,
            ])
            ->callAction('editOrganic', data: [
                'organic_certifier' => 'OCCP',
                'organic_certificate_no' => 'A-1',
                'organic_certified_until' => now()->addYear()->toDateString(),
            ])
            ->assertHasNoFormErrors();

        $fresh = $farm->fresh();
        $this->assertSame('Admin description', $fresh->description);
        $this->assertFalse($fresh->is_active);
        $this->assertSame('OCCP', $fresh->organic_certifier);
        $this->assertTrue($fresh->isOrganicCertified());
    }

    public function test_a_content_editor_edits_profile_fields_but_not_organic_or_status(): void
    {
        $farm = Farm::factory()->create([
            'is_active' => true,
            'organic_certifier' => null,
            'value_added_enabled' => true,
            'reservations_enabled' => true,
            'tawad_enabled' => true,
            'walk_in_enabled' => true,
        ]);
        $editor = $this->staff(Role::ContentEditor, $farm);

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->callAction('editAbout', data: [
                'description' => 'Morning harvest.',
            ])
            ->callAction('editContact', data: [
                'contact_person' => 'Aling Nena',
                'contact_number' => '09171230001',
                'pickup_point' => 'Barangay hall',
                'address' => 'Farm road',
                'barangay' => 'Manggahan',
                'municipality' => 'General Trias',
            ])
            ->callAction('editName', data: [
                'name' => 'Manggahan Farm',
                'slug' => 'manggahan-farm',
            ])
            ->callAction('editLocation', data: [
                'latitude' => '14.38690',
                'longitude' => '120.88030',
            ])
            ->callAction('editFeatures', data: [
                'reservations_enabled' => false,
            ])
            ->assertHasNoFormErrors()
            ->assertActionHidden('editOrganic')
            ->assertActionHidden('editStatus');

        $fresh = $farm->fresh();
        $this->assertSame('Morning harvest.', $fresh->description);
        $this->assertSame('Aling Nena', $fresh->contact_person);
        $this->assertSame('09171230001', $fresh->contact_number);
        $this->assertSame('Manggahan Farm', $fresh->name);
        $this->assertSame('manggahan-farm', $fresh->slug);
        $this->assertEquals(14.3869, (float) $fresh->latitude);
        $this->assertEquals(120.8803, (float) $fresh->longitude);
        $this->assertFalse($fresh->reservations_enabled);
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->organic_certifier);

        $warning = json_encode(array_merge(
            session('filament.notifications', []),
            session('filament.claimed_notifications', []),
        ));
        $this->assertIsString($warning);
        $this->assertStringContainsString('Reservations turned off', $warning);
    }

    public function test_a_location_outside_the_philippines_is_rejected(): void
    {
        $farm = Farm::factory()->create();
        $admin = $this->staff(Role::SuperAdmin);

        $preview = Livewire::actingAs($admin)
            ->test(EditFarm::class, ['record' => $farm->id]);
        $preview->mountAction('editLocation');
        $schema = new ReflectionMethod($preview->instance(), 'getMountedActionSchema');
        $html = $schema->invoke($preview->instance())->toHtml();
        $this->assertStringContainsString('farmLocationMap', $html);
        $this->assertStringContainsString('wire:ignore', $html);
        $this->assertStringContainsString('latitudePath\u0022:\u0022mountedActions.0.data.latitude', $html);
        $this->assertStringContainsString('longitudePath\u0022:\u0022mountedActions.0.data.longitude', $html);

        Livewire::actingAs($admin)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->callAction('editLocation', data: [
                'latitude' => 50,
                'longitude' => 120.88,
            ])
            ->assertHasFormErrors(['latitude']);

        $this->assertNull($farm->fresh()->latitude);
        $this->assertNull($farm->fresh()->longitude);
    }

    public function test_remove_pin_clears_both_coordinates(): void
    {
        $farm = Farm::factory()->create([
            'latitude' => 14.3869,
            'longitude' => 120.8803,
        ]);
        $editor = $this->staff(Role::ContentEditor, $farm);

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->callAction('editLocation.removePin')
            ->assertHasNoFormErrors();

        $fresh = $farm->fresh();
        $this->assertNull($fresh->latitude);
        $this->assertNull($fresh->longitude);
    }

    public function test_logo_is_stored_replaced_deleted_and_exposed(): void
    {
        Storage::fake(ListingStorage::diskName());
        $images = app(ImageVariants::class);
        $farm = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);

        Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->callAction('editLogo', data: [
                'logo_path' => [UploadedFile::fake()->image('logo.jpg', 400, 400)],
            ])
            ->assertHasNoFormErrors();

        $farm->refresh();
        $first = $farm->logo_path;
        $this->assertIsString($first);
        $thumb = $images->thumbnailPath($first);
        ListingStorage::disk()->assertExists($first);
        ListingStorage::disk()->assertExists($thumb);

        $buyer = $this->buyer();
        $this->asUser($buyer)
            ->getJson('/api/farms/'.$farm->id)
            ->assertOk()
            ->assertJsonPath('data.logo_url', ListingStorage::disk()->url($first))
            ->assertJsonPath('data.logo_thumbnail_url', ListingStorage::disk()->url($thumb));

        $second = $images->store(UploadedFile::fake()->image('next.jpg', 400, 400), 'farms/logos');
        $farm->update(['logo_path' => $second]);

        ListingStorage::disk()->assertMissing($first);
        ListingStorage::disk()->assertMissing($thumb);
        ListingStorage::disk()->assertExists($second);

        $farm->delete();
        ListingStorage::disk()->assertMissing($second);
        ListingStorage::disk()->assertMissing($images->thumbnailPath($second));
    }

    public function test_place_search_checks_permission_sends_a_user_agent_caches_and_rate_limits(): void
    {
        $farm = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $buyer = $this->buyer();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '14.38690',
                    'lon' => '120.88030',
                    'display_name' => 'General Trias, Cavite',
                ],
            ]),
        ]);

        $this->actingAs($buyer);
        $denied = new EditFarm;
        $denied->record = $farm;

        try {
            $denied->searchFarmPlaces('General Trias');
            $this->fail('A buyer searched for a farm place.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Http::assertNothingSent();

        $page = Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->instance();

        $first = $page->searchFarmPlaces('General Trias');
        $this->assertSame('General Trias, Cavite', $first['places'][0]['label']);
        $this->assertNull($first['message']);

        $this->travel(2)->seconds();
        $second = $page->searchFarmPlaces('General Trias');
        $this->assertSame($first['places'], $second['places']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->hasHeader('User-Agent', NominatimPlaceSearch::USER_AGENT)
                && str_contains($request->url(), 'countrycodes=ph')
                && str_contains($request->url(), 'format=jsonv2');
        });

        $busy = $page->searchFarmPlaces('Tanza market');
        $this->assertSame(NominatimPlaceSearch::BUSY_MESSAGE, $busy['message']);
        $this->assertSame([], $busy['places']);
        Http::assertSentCount(1);
    }

    public function test_google_maps_links_are_parsed_and_unsafe_hops_are_rejected(): void
    {
        $farm = Farm::factory()->create();
        $editor = $this->staff(Role::ContentEditor, $farm);
        $resolver = app(GoogleMapsLinkResolver::class);

        Http::fake(function ($request) {
            $url = $request->url();

            return match ($url) {
                'https://maps.app.goo.gl/abc' => Http::response('', 302, [
                    'Location' => 'https://evil.example/maps/@14.1,120.9',
                ]),
                'https://goo.gl/abc' => Http::response('', 302, [
                    'Location' => 'http://maps.google.com/maps?q=14.1,120.9',
                ]),
                'https://maps.app.goo.gl/ip' => Http::response('', 302, [
                    'Location' => 'https://8.8.8.8/maps/@14.1,120.9',
                ]),
                'https://maps.app.goo.gl/a' => Http::response('', 302, [
                    'Location' => 'https://maps.app.goo.gl/b',
                ]),
                'https://maps.app.goo.gl/b' => Http::response('', 302, [
                    'Location' => 'https://maps.app.goo.gl/c',
                ]),
                'https://maps.app.goo.gl/c' => Http::response('', 302, [
                    'Location' => 'https://www.google.com/maps/@14.25000,120.95000,15z',
                ]),
                'https://www.google.com/maps/@14.25000,120.95000,15z' => Http::response('should not be fetched', 200),
                default => Http::response('ok', 200),
            };
        });

        $formats = [
            'https://www.google.com/maps/@14.38690,120.88030,15z' => [14.38690, 120.88030],
            'https://www.google.com/maps/place/data=!3d14.10000!4d120.20000' => [14.1, 120.2],
            'https://maps.google.com/maps?q=14.50000,121.00000' => [14.5, 121.0],
            'https://www.google.com/maps?ll=13.20000,123.40000' => [13.2, 123.4],
        ];

        foreach ($formats as $url => $expected) {
            $parsed = $resolver->resolve($url);
            $this->assertEquals($expected[0], $parsed['latitude']);
            $this->assertEquals($expected[1], $parsed['longitude']);
            $this->assertNull($parsed['message']);
        }

        $this->assertSame(
            GoogleMapsLinkResolver::UNREADABLE,
            $resolver->resolve('https://maps.app.goo.gl/abc')['message'],
        );
        $this->assertSame(
            GoogleMapsLinkResolver::UNREADABLE,
            $resolver->resolve('https://goo.gl/abc')['message'],
        );
        $this->assertSame(
            GoogleMapsLinkResolver::UNREADABLE,
            $resolver->resolve('https://maps.app.goo.gl/ip')['message'],
        );
        $this->assertSame(
            GoogleMapsLinkResolver::UNREADABLE,
            $resolver->resolve('http://www.google.com/maps/@14.1,120.9,15z')['message'],
        );

        $stopped = $resolver->resolve('https://maps.app.goo.gl/a');
        $this->assertSame(GoogleMapsLinkResolver::UNREADABLE, $stopped['message']);

        $requested = collect(Http::recorded())
            ->map(fn (array $pair): string => $pair[0]->url())
            ->all();

        $this->assertContains('https://maps.app.goo.gl/a', $requested);
        $this->assertContains('https://maps.app.goo.gl/b', $requested);
        $this->assertContains('https://maps.app.goo.gl/c', $requested);
        $this->assertNotContains('https://www.google.com/maps/@14.25000,120.95000,15z', $requested);
        $this->assertNotContains('https://evil.example/maps/@14.1,120.9', $requested);
        $this->assertNotContains('http://maps.google.com/maps?q=14.1,120.9', $requested);
        $this->assertNotContains('https://8.8.8.8/maps/@14.1,120.9', $requested);
        $this->assertNotContains('http://www.google.com/maps/@14.1,120.9,15z', $requested);

        $page = Livewire::actingAs($editor)
            ->test(EditFarm::class, ['record' => $farm->id])
            ->instance();
        $unreadable = $page->resolveFarmMapLink('https://maps.google.com/maps/place/Somewhere');
        $this->assertSame(GoogleMapsLinkResolver::UNREADABLE, $unreadable['message']);
        $this->assertNull($unreadable['latitude']);
    }

    public function test_permissions_policy_allows_geolocation_for_this_site_only(): void
    {
        $response = $this->get('/');

        $policy = (string) $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
