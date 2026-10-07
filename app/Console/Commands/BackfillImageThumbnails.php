<?php

namespace App\Console\Commands;

use App\Models\CropCareArticle;
use App\Models\Farm;
use App\Models\FarmPhoto;
use App\Models\Listing;
use App\Support\ImageVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('images:backfill-thumbnails')]
#[Description('Create missing 400px thumbnails for listing, farm, and crop-care images')]
class BackfillImageThumbnails extends Command
{
    public function handle(ImageVariants $images): int
    {
        $created = 0;

        $paths = collect()
            ->merge(Listing::query()->withTrashed()->whereNotNull('image_path')->pluck('image_path'))
            ->merge(Farm::query()->whereNotNull('cover_photo_path')->pluck('cover_photo_path'))
            ->merge(FarmPhoto::query()->whereNotNull('path')->pluck('path'))
            ->merge(CropCareArticle::query()->withTrashed()->whereNotNull('image_path')->pluck('image_path'))
            ->filter()
            ->unique()
            ->values();

        foreach ($paths as $path) {
            if ($images->backfill((string) $path)) {
                $created++;
            }
        }

        $this->info("Created {$created} missing thumbnail".($created === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
