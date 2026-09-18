<?php

namespace App\Actions\Listings;

use App\Support\ListingStorage;
use Illuminate\Http\UploadedFile;

class SyncListingImage
{
    public function store(UploadedFile $file): string
    {
        return $file->store('listings', ListingStorage::diskName());
    }

    public function replace(?string $currentPath, UploadedFile $file): string
    {
        $this->delete($currentPath);

        return $this->store($file);
    }

    public function delete(?string $path): void
    {
        if (filled($path)) {
            ListingStorage::disk()->delete($path);
        }
    }
}
