<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicListingImageServeTest extends TestCase
{
    public function test_listing_images_are_served_from_the_public_disk_without_a_signature(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs(
            'listings',
            UploadedFile::fake()->image('demo-nena-kamatis.jpg', 40, 30),
            'demo-nena-kamatis.jpg',
        );

        $this->get('/storage/listings/demo-nena-kamatis.jpg')
            ->assertOk();
    }

    public function test_missing_public_images_are_not_found(): void
    {
        Storage::fake('public');

        $this->get('/storage/listings/missing.jpg')
            ->assertNotFound();
    }
}
