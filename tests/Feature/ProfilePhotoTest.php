<?php

namespace Tests\Feature;

use App\Actions\Privacy\AnonymizeUserAction;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake(ListingStorage::diskName());
    }

    public function test_public_shop_responses_hide_seller_contact_and_phone(): void
    {
        $farmer = $this->farmer([
            'shop_name' => 'Juan Farm Stall',
            'phone' => '09170000111',
            'contact' => '09170000222',
        ]);
        $buyer = $this->buyer();

        $show = $this->asUser($buyer)->getJson("/api/buyer/shops/{$farmer->id}");
        $show->assertOk()
            ->assertJsonPath('data.shop_name', 'Juan Farm Stall')
            ->assertJsonMissingPath('data.contact')
            ->assertJsonMissingPath('data.phone');
        $show->assertJsonMissing(['09170000111']);
        $show->assertJsonMissing(['09170000222']);

        $index = $this->asUser($buyer)->getJson('/api/buyer/shops');
        $index->assertOk()
            ->assertJsonMissingPath('data.0.contact')
            ->assertJsonMissingPath('data.0.phone');
        $index->assertJsonMissing(['09170000111']);
        $index->assertJsonMissing(['09170000222']);

        $this->asUser($farmer)
            ->getJson('/api/farmer/shop')
            ->assertOk()
            ->assertJsonPath('data.contact', '09170000222');
    }

    public function test_user_can_upload_replace_and_delete_an_avatar(): void
    {
        $buyer = $this->buyer();
        $images = app(ImageVariants::class);
        $disk = ListingStorage::disk();

        $created = $this->asUser($buyer)
            ->post('/api/auth/user/avatar', [
                'image' => UploadedFile::fake()->image('face.jpg', 640, 480),
            ], ['Accept' => 'application/json']);
        $created->assertOk();
        $this->assertNotEmpty($created->json('data.avatar_url'));

        $buyer->refresh();
        $first = $buyer->avatar_path;
        $firstThumb = $images->thumbnailPath((string) $first);
        $this->assertNotNull($first);
        $disk->assertExists($first);
        $disk->assertExists($firstThumb);

        $this->asUser($buyer)
            ->post('/api/auth/user/avatar', [
                'image' => UploadedFile::fake()->image('replacement.jpg', 320, 320),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $buyer->refresh();
        $this->assertNotSame($first, $buyer->avatar_path);
        $disk->assertMissing($first);
        $disk->assertMissing($firstThumb);
        $disk->assertExists((string) $buyer->avatar_path);
        $disk->assertExists($images->thumbnailPath((string) $buyer->avatar_path));

        $removed = (string) $buyer->avatar_path;
        $removedThumb = $images->thumbnailPath($removed);

        $this->asUser($buyer)
            ->deleteJson('/api/auth/user/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $buyer->refresh();
        $this->assertNull($buyer->avatar_path);
        $disk->assertMissing($removed);
        $disk->assertMissing($removedThumb);
    }

    public function test_avatar_upload_rejects_the_wrong_type_and_an_oversized_file(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->post('/api/auth/user/avatar', [
                'image' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->asUser($buyer)
            ->post('/api/auth/user/avatar', [
                'image' => UploadedFile::fake()->image('huge.jpg')->size(3000),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertNull($buyer->refresh()->avatar_path);
    }

    public function test_farmer_can_upload_and_delete_a_storefront_cover_and_a_buyer_cannot(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Covered Stall']);
        $buyer = $this->buyer();
        $images = app(ImageVariants::class);
        $disk = ListingStorage::disk();

        $this->asUser($buyer)
            ->post('/api/farmer/shop/cover', [
                'image' => UploadedFile::fake()->image('banner.jpg', 800, 300),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $stored = $this->asUser($farmer)
            ->post('/api/farmer/shop/cover', [
                'image' => UploadedFile::fake()->image('banner.jpg', 800, 300),
            ], ['Accept' => 'application/json']);
        $stored->assertOk();
        $this->assertNotEmpty($stored->json('data.cover_url'));

        $farmer->refresh();
        $cover = (string) $farmer->cover_photo_path;
        $this->assertNotSame('', $cover);
        $disk->assertExists($cover);

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonPath('data.avatar_url', $farmer->avatarUrl())
            ->assertJsonPath('data.cover_url', $farmer->coverUrl());

        $this->asUser($farmer)
            ->deleteJson('/api/farmer/shop/cover')
            ->assertOk()
            ->assertJsonPath('data.cover_url', null);

        $farmer->refresh();
        $this->assertNull($farmer->cover_photo_path);
        $disk->assertMissing($cover);
        $disk->assertMissing($images->thumbnailPath($cover));
    }

    public function test_shop_profile_includes_the_seller_avatar(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Avatar Stall']);
        $farmer->avatar_path = app(ImageVariants::class)->store(
            UploadedFile::fake()->image('face.jpg', 200, 200),
            'avatars',
        );
        $farmer->save();
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonPath('data.avatar_url', $farmer->avatarUrl());
    }

    public function test_own_data_export_includes_avatar_and_cover_urls(): void
    {
        $buyer = $this->buyer(['email' => 'ana@example.com']);
        $images = app(ImageVariants::class);
        $buyer->forceFill([
            'avatar_path' => $images->store(UploadedFile::fake()->image('face.jpg', 80, 80), 'avatars'),
            'cover_photo_path' => $images->store(UploadedFile::fake()->image('banner.jpg', 200, 80), 'shop-covers'),
        ])->save();

        $payload = json_decode(
            $this->asUser($buyer)->get('/api/auth/user/export')->assertOk()->streamedContent(),
            true,
        );

        $this->assertSame($buyer->avatarUrl(), $payload['profile']['avatar_url']);
        $this->assertSame($buyer->coverUrl(), $payload['profile']['cover_url']);
    }

    public function test_account_deletion_removes_avatar_and_cover_files(): void
    {
        $buyer = $this->buyer();
        $images = app(ImageVariants::class);
        $avatar = $images->store(UploadedFile::fake()->image('face.jpg', 80, 80), 'avatars');
        $cover = $images->store(UploadedFile::fake()->image('banner.jpg', 200, 80), 'shop-covers');
        $buyer->forceFill([
            'avatar_path' => $avatar,
            'cover_photo_path' => $cover,
        ])->save();

        app(AnonymizeUserAction::class)->handle($buyer);

        $disk = ListingStorage::disk();
        $disk->assertMissing($avatar);
        $disk->assertMissing($images->thumbnailPath($avatar));
        $disk->assertMissing($cover);
        $disk->assertMissing($images->thumbnailPath($cover));

        $deleted = User::withTrashed()->findOrFail($buyer->id);
        $this->assertNull($deleted->avatar_path);
        $this->assertNull($deleted->cover_photo_path);
    }

    public function test_seller_order_does_not_include_the_buyer_location(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 25, 'quantity_available' => 10]);
        $buyer = $this->buyer([
            'location' => 'Secret Sitio 42',
            'phone' => '09175550000',
        ]);
        $order = $this->placeOrder($buyer, $listing, 1);

        $response = $this->asUser($farmer)->getJson("/api/farmer/orders/{$order->id}");
        $response->assertOk();
        $response->assertJsonMissing(['Secret Sitio 42']);
        $this->assertArrayNotHasKey('location', $response->json('data.buyer'));
        $response->assertJsonPath('data.buyer.contact', '09175550000');
    }
}
