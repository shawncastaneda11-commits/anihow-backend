<?php

namespace Tests\Feature;

use App\Models\CropCareArticle;
use App\Models\Farm;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class StoragePruneOrphansTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake(ListingStorage::diskName());
    }

    public function test_replacing_farm_cover_and_gallery_photo_deletes_the_old_files(): void
    {
        $images = app(ImageVariants::class);
        $farm = Farm::factory()->create([
            'cover_photo_path' => $images->store(UploadedFile::fake()->image('cover.jpg', 400, 300), 'farms'),
        ]);
        $oldCover = $farm->cover_photo_path;
        $oldCoverThumb = $images->thumbnailPath($oldCover);
        $photo = $farm->photos()->create([
            'path' => $images->store(UploadedFile::fake()->image('gallery.jpg', 400, 300), 'farms/'.$farm->id),
            'caption' => 'Crates',
            'sort_order' => 1,
        ]);
        $oldPhoto = $photo->path;
        $oldPhotoThumb = $images->thumbnailPath($oldPhoto);
        $disk = Storage::disk(ListingStorage::diskName());

        $farm->update([
            'cover_photo_path' => $images->store(UploadedFile::fake()->image('next.jpg', 400, 300), 'farms'),
        ]);
        $disk->assertMissing($oldCover);
        $disk->assertMissing($oldCoverThumb);
        $disk->assertExists($farm->fresh()->cover_photo_path);

        $photo->update([
            'path' => $images->store(UploadedFile::fake()->image('next-gallery.jpg', 400, 300), 'farms/'.$farm->id),
        ]);
        $disk->assertMissing($oldPhoto);
        $disk->assertMissing($oldPhotoThumb);

        $kept = $photo->fresh()->path;
        $photo->delete();
        $disk->assertMissing($kept);
        $disk->assertMissing($images->thumbnailPath($kept));
    }

    public function test_replacing_crop_care_image_deletes_the_old_file(): void
    {
        $images = app(ImageVariants::class);
        $farm = $this->farm();
        $author = $this->farmer([], $farm);
        $article = CropCareArticle::factory()->forFarm($farm, $author)->create([
            'image_path' => $images->store(UploadedFile::fake()->image('care.jpg', 400, 300), 'crop-care'),
        ]);
        $old = $article->image_path;
        $disk = Storage::disk(ListingStorage::diskName());

        $article->update([
            'image_path' => $images->store(UploadedFile::fake()->image('care-next.jpg', 400, 300), 'crop-care'),
        ]);

        $disk->assertMissing($old);
        $disk->assertMissing($images->thumbnailPath($old));
        $disk->assertExists($article->fresh()->image_path);
    }

    public function test_prune_orphans_dry_run_lists_and_keeps_unreferenced_files(): void
    {
        $images = app(ImageVariants::class);
        $kept = $images->store(UploadedFile::fake()->image('kept.jpg', 400, 300), 'listings');
        Farm::factory()->create(['cover_photo_path' => $kept]);

        $disk = Storage::disk(ListingStorage::diskName());
        $orphan = 'listings/orphan.jpg';
        $disk->put($orphan, UploadedFile::fake()->image('orphan.jpg', 80, 80)->getContent());

        Artisan::call('storage:prune-orphans', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString($orphan, $output);
        $this->assertStringNotContainsString($kept, $output);
        $disk->assertExists($orphan);
        $disk->assertExists($kept);
        $disk->assertExists($images->thumbnailPath($kept));
    }

    public function test_prune_orphans_deletes_unreferenced_files_and_keeps_referenced_ones(): void
    {
        $images = app(ImageVariants::class);
        $kept = $images->store(UploadedFile::fake()->image('kept.jpg', 400, 300), 'farms');
        Farm::factory()->create(['cover_photo_path' => $kept]);

        $disk = Storage::disk(ListingStorage::diskName());
        $orphan = 'articles/leftover.jpg';
        $disk->put($orphan, UploadedFile::fake()->image('leftover.jpg', 80, 80)->getContent());

        Artisan::call('storage:prune-orphans');

        $disk->assertMissing($orphan);
        $disk->assertExists($kept);
        $disk->assertExists($images->thumbnailPath($kept));
    }

    public function test_prune_orphans_keeps_files_referenced_only_by_listing_photos(): void
    {
        $images = app(ImageVariants::class);
        $listing = $this->listingFor($this->farmer());
        $path = $images->store(UploadedFile::fake()->image('extra.jpg', 400, 300), 'listings');
        $listing->photos()->create([
            'path' => $path,
            'sort_order' => 1,
        ]);

        $this->assertNull($listing->image_path);

        Artisan::call('storage:prune-orphans');

        $disk = Storage::disk(ListingStorage::diskName());
        $disk->assertExists($path);
        $disk->assertExists($images->thumbnailPath($path));
    }
}
