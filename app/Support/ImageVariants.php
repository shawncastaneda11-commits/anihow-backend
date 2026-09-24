<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Stores a downscaled original and a 400px thumbnail. JPEG only, EXIF stripped.
 */
class ImageVariants
{
    public const MAX_LONG_SIDE = 1600;

    public const THUMB_LONG_SIDE = 400;

    public const JPEG_QUALITY = 82;

    public function store(UploadedFile $file, string $directory): string
    {
        $basename = Str::uuid()->toString();
        $path = trim($directory, '/').'/'.$basename.'.jpg';

        $this->writeVariants($this->loadUploaded($file), $path);

        return $path;
    }

    public function replace(?string $currentPath, UploadedFile $file, string $directory): string
    {
        $this->delete($currentPath);

        return $this->store($file, $directory);
    }

    public function delete(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $disk = ListingStorage::disk();
        $disk->delete($path);
        $disk->delete($this->thumbnailPath($path));
    }

    public function thumbnailPath(string $path): string
    {
        $directory = trim(str_replace('\\', '/', dirname($path)), '.');
        $filename = pathinfo($path, PATHINFO_FILENAME);

        return ($directory === '' ? '' : $directory.'/').$filename.'_thumb.jpg';
    }

    public function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return ListingStorage::disk()->url($path);
    }

    public function thumbnailUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $thumb = $this->thumbnailPath($path);

        if (! ListingStorage::disk()->exists($thumb)) {
            return null;
        }

        return ListingStorage::disk()->url($thumb);
    }

    /**
     * Create a missing thumbnail next to an existing original. Safe to rerun.
     */
    public function backfill(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $disk = ListingStorage::disk();

        if (! $disk->exists($path)) {
            return false;
        }

        $thumb = $this->thumbnailPath($path);

        if ($disk->exists($thumb)) {
            return false;
        }

        $image = @imagecreatefromstring((string) $disk->get($path));

        if ($image === false) {
            return false;
        }

        $this->writeJpeg($thumb, $this->fit($image, self::THUMB_LONG_SIDE));
        imagedestroy($image);

        return true;
    }

    private function loadUploaded(UploadedFile $file): \GdImage
    {
        $realPath = $file->getRealPath();
        $contents = is_string($realPath) && is_file($realPath)
            ? (string) file_get_contents($realPath)
            : (string) $file->getContent();

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new \RuntimeException('The uploaded image could not be read.');
        }

        return $this->orient($image, is_string($realPath) ? $realPath : null);
    }

    private function writeVariants(\GdImage $image, string $path): void
    {
        $full = $this->fit($image, self::MAX_LONG_SIDE);
        $thumb = $this->fit($full, self::THUMB_LONG_SIDE);
        $this->writeJpeg($path, $full);
        $this->writeJpeg($this->thumbnailPath($path), $thumb);

        if ($thumb !== $full && $thumb !== $image) {
            imagedestroy($thumb);
        }

        if ($full !== $image) {
            imagedestroy($full);
        }

        imagedestroy($image);
    }

    private function fit(\GdImage $image, int $maxLongSide): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $long = max($width, $height);

        if ($long <= $maxLongSide) {
            return $image;
        }

        $scale = $maxLongSide / $long;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $resized;
    }

    private function orient(\GdImage $image, ?string $path): \GdImage
    {
        if ($path === null || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function writeJpeg(string $path, \GdImage $image): void
    {
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $bytes = (string) ob_get_clean();

        ListingStorage::disk()->put($path, $bytes);
    }

    /**
     * Filament FileUpload: persist variants and delete both files on remove.
     *
     * @param  FileUpload  $upload
     * @return FileUpload
     */
    public static function bindUpload(object $upload, string $directory): object
    {
        return $upload
            ->saveUploadedFileUsing(function (TemporaryUploadedFile|UploadedFile $file) use ($directory): string {
                return app(self::class)->store($file, $directory);
            })
            ->deleteUploadedFileUsing(function (?string $file): void {
                app(self::class)->delete($file);
            });
    }
}
