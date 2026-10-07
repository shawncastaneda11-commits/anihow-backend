<?php

namespace App\Actions\Listings;

use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;

class SyncListingImage
{
    public function __construct(private ImageVariants $images) {}

    public function store(UploadedFile $file): string
    {
        return $this->images->store($file, 'listings');
    }

    public function replace(?string $currentPath, UploadedFile $file): string
    {
        return $this->images->replace($currentPath, $file, 'listings');
    }

    public function delete(?string $path): void
    {
        $this->images->delete($path);
    }
}
