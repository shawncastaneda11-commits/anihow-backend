<?php

namespace App\Actions\CropCare;

use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;

class SyncCropCareImage
{
    public function __construct(private ImageVariants $images) {}

    public function store(UploadedFile $file): string
    {
        return $this->images->store($file, 'crop-care');
    }

    public function replace(?string $currentPath, UploadedFile $file): string
    {
        return $this->images->replace($currentPath, $file, 'crop-care');
    }

    public function delete(?string $path): void
    {
        $this->images->delete($path);
    }
}
