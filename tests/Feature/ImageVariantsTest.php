<?php

namespace Tests\Feature;

use App\Models\CropCareArticle;
use App\Models\Farm;
use App\Models\Listing;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ImageVariantsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake(ListingStorage::diskName());
    }

    public function test_listing_upload_creates_original_and_thumbnail_within_max_sizes(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType();

        $create = $this->asUser($farmer)->post('/api/farmer/listings', [
            'title' => 'Fresh kamatis, hand picked',
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'image' => UploadedFile::fake()->image('produce.jpg', 2000, 800),
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Fresh kamatis, hand picked');
        $this->assertNotEmpty($create->json('data.image_url'));
        $this->assertNotEmpty($create->json('data.thumbnail_url'));

        $listing = Listing::query()->findOrFail($create->json('data.id'));
        $disk = Storage::disk(ListingStorage::diskName());
        $thumb = app(ImageVariants::class)->thumbnailPath($listing->image_path);

        $disk->assertExists($listing->image_path);
        $disk->assertExists($thumb);
        $this->assertLongSideAtMost($disk->get($listing->image_path), ImageVariants::MAX_LONG_SIDE);
        $this->assertLongSideAtMost($disk->get($thumb), ImageVariants::THUMB_LONG_SIDE);
    }

    public function test_replace_and_force_delete_remove_both_image_files(): void
    {
        $farmer = $this->farmer();
        $cropType = $this->cropType();
        $images = app(ImageVariants::class);

        $create = $this->asUser($farmer)->post('/api/farmer/listings', [
            'title' => 'Fresh kamatis, hand picked',
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'image' => UploadedFile::fake()->image('first.jpg', 640, 480),
        ], ['Accept' => 'application/json'])->assertCreated();

        $listing = Listing::query()->findOrFail($create->json('data.id'));
        $original = $listing->image_path;
        $originalThumb = $images->thumbnailPath($original);
        $disk = Storage::disk(ListingStorage::diskName());

        $this->asUser($farmer)->post('/api/farmer/listings/'.$listing->id, [
            'title' => 'Kamatis, bagong ani',
            'image' => UploadedFile::fake()->image('second.jpg', 640, 480),
        ], ['Accept' => 'application/json'])->assertOk();

        $replaced = $listing->fresh();
        $this->assertNotSame($original, $replaced->image_path);
        $disk->assertMissing($original);
        $disk->assertMissing($originalThumb);
        $disk->assertExists($replaced->image_path);
        $disk->assertExists($images->thumbnailPath($replaced->image_path));

        $kept = $replaced->image_path;
        $keptThumb = $images->thumbnailPath($kept);
        $replaced->forceDelete();
        $disk->assertMissing($kept);
        $disk->assertMissing($keptThumb);
    }

    public function test_resources_expose_thumbnail_url_and_farm_fields_stay_in_place(): void
    {
        $images = app(ImageVariants::class);
        $farmer = $this->farmer();
        $cropType = $this->cropType();

        $create = $this->asUser($farmer)->post('/api/farmer/listings', [
            'title' => 'Fresh kamatis, hand picked',
            'crop_type_id' => $cropType->id,
            'price_per_unit' => 30,
            'quantity_available' => 20,
            'image' => UploadedFile::fake()->image('produce.jpg', 400, 300),
        ], ['Accept' => 'application/json'])->assertCreated();

        $listing = Listing::query()->findOrFail($create->json('data.id'));

        $this->asUser($this->buyer())
            ->getJson('/api/buyer/marketplace/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.image_url', $listing->imageUrl())
            ->assertJsonPath('data.thumbnail_url', $listing->thumbnailUrl());

        $farm = $farmer->farm;
        $this->assertNotNull($farm);
        $cover = $images->store(UploadedFile::fake()->image('cover.jpg', 800, 400), 'farms');
        $farm->update(['cover_photo_path' => $cover, 'is_active' => true]);
        $gallery = $images->store(UploadedFile::fake()->image('gallery.jpg', 600, 400), 'farms/'.$farm->id);
        $farm->photos()->create(['path' => $gallery, 'caption' => 'Crates', 'sort_order' => 1]);

        $this->asUser($this->buyer())
            ->getJson('/api/farms/'.$farm->id)
            ->assertOk()
            ->assertJsonPath('data.cover_photo_url', $farm->fresh()->coverPhotoUrl())
            ->assertJsonPath('data.thumbnail_url', $farm->fresh()->coverThumbnailUrl())
            ->assertJsonPath('data.photos.0.url', $farm->fresh()->photos->first()->url())
            ->assertJsonPath('data.photos.0.thumbnail_url', $farm->fresh()->photos->first()->thumbnailUrl());

        $article = CropCareArticle::factory()->forFarm($farm, $farmer)->create([
            'image_path' => $images->store(UploadedFile::fake()->image('care.jpg', 500, 400), 'crop-care'),
        ]);

        $this->asUser($farmer)
            ->getJson('/api/crop-care/'.$article->id)
            ->assertOk()
            ->assertJsonPath('data.image_url', $article->fresh()->imageUrl())
            ->assertJsonPath('data.thumbnail_url', $article->fresh()->thumbnailUrl());
    }

    public function test_thumbnail_backfill_is_idempotent(): void
    {
        $images = app(ImageVariants::class);
        $disk = Storage::disk(ListingStorage::diskName());
        $path = 'listings/legacy.jpg';
        $disk->put($path, UploadedFile::fake()->image('legacy.jpg', 800, 600)->getContent());

        Farm::factory()->create(['cover_photo_path' => $path]);

        $this->assertFalse($disk->exists($images->thumbnailPath($path)));

        Artisan::call('images:backfill-thumbnails');
        $this->assertTrue($disk->exists($images->thumbnailPath($path)));
        $first = $disk->get($images->thumbnailPath($path));

        Artisan::call('images:backfill-thumbnails');
        $this->assertSame($first, $disk->get($images->thumbnailPath($path)));
        $this->assertLongSideAtMost($first, ImageVariants::THUMB_LONG_SIDE);
    }

    private function assertLongSideAtMost(string $bytes, int $max): void
    {
        $info = getimagesizefromstring($bytes);
        $this->assertIsArray($info);
        $this->assertLessThanOrEqual($max, max((int) $info[0], (int) $info[1]));
    }
}
