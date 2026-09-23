<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Support\ListingStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ListingImageSeeder extends Seeder
{
    /**
     * Attach cover photos to listings that still have none.
     *
     * @var array<string, string>
     */
    private array $keywords = [
        'kamatis' => 'tomato.jpg',
        'tomato' => 'tomato.jpg',
        'kamote' => 'kamote.jpg',
        'mango' => 'mango.jpg',
        'mangga' => 'mango.jpg',
        'banana' => 'banana.jpg',
        'saging' => 'banana.jpg',
        'sitaw' => 'sitaw.jpg',
        'ampalaya' => 'ampalaya.jpg',
    ];

    public function run(): void
    {
        $sourceDir = public_path('images/produce');
        $disk = ListingStorage::disk();

        foreach (Listing::query()->whereNull('image_path')->orWhere('image_path', '')->cursor() as $listing) {
            $file = $this->fileFor($listing->title);
            if ($file === null) {
                continue;
            }

            $source = $sourceDir.DIRECTORY_SEPARATOR.$file;
            if (! is_file($source)) {
                continue;
            }

            $path = 'listings/'.$file;
            if (! $disk->exists($path)) {
                $disk->put($path, File::get($source));
            }

            $listing->forceFill(['image_path' => $path])->save();
        }
    }

    private function fileFor(string $title): ?string
    {
        $haystack = mb_strtolower($title);
        foreach ($this->keywords as $keyword => $file) {
            if (str_contains($haystack, $keyword)) {
                return $file;
            }
        }

        return 'tomato.jpg';
    }
}
