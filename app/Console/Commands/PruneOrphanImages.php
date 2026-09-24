<?php

namespace App\Console\Commands;

use App\Models\CropCareArticle;
use App\Models\Farm;
use App\Models\FarmPhoto;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('storage:prune-orphans {--dry-run : List orphans without deleting them}')]
#[Description('Delete image files under farms/, listings/, and article directories that no row references')]
class PruneOrphanImages extends Command
{
    /**
     * @var list<string>
     */
    private const DIRECTORIES = ['farms', 'listings', 'crop-care', 'articles'];

    public function handle(ImageVariants $images): int
    {
        $disk = ListingStorage::disk();
        $referenced = $this->referencedPaths($images);
        $orphans = [];

        foreach (self::DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $file) {
                $path = str_replace('\\', '/', $file);

                if (! isset($referenced[$path])) {
                    $orphans[] = $path;
                }
            }
        }

        if ($orphans === []) {
            $this->info('No orphaned image files.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($orphans as $path) {
            $this->line($path);

            if (! $dryRun) {
                $disk->delete($path);
            }
        }

        $count = count($orphans);
        $this->info($dryRun
            ? "{$count} orphaned file".($count === 1 ? '' : 's').' would be deleted.'
            : "Deleted {$count} orphaned file".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function referencedPaths(ImageVariants $images): array
    {
        $paths = collect()
            ->merge(Listing::query()->withTrashed()->whereNotNull('image_path')->pluck('image_path'))
            ->merge(Farm::query()->whereNotNull('cover_photo_path')->pluck('cover_photo_path'))
            ->merge(FarmPhoto::query()->whereNotNull('path')->pluck('path'))
            ->merge(ListingPhoto::query()->whereNotNull('path')->pluck('path'))
            ->merge(CropCareArticle::query()->withTrashed()->whereNotNull('image_path')->pluck('image_path'))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => str_replace('\\', '/', $path))
            ->unique()
            ->values();

        $referenced = [];

        foreach ($paths as $path) {
            $referenced[$path] = true;
            $referenced[$images->thumbnailPath($path)] = true;
        }

        return $referenced;
    }
}
