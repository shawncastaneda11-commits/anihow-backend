<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Stores a downscaled original and a 400px thumbnail. JPEG only, EXIF stripped.
 */
class ImageVariants
{
    public const MAX_LONG_SIDE = 1600;

    public const THUMB_LONG_SIDE = 400;

    public const JPEG_QUALITY = 82;

    public const MAX_PIXELS = 40_000_000;

    public function store(UploadedFile $file, string $directory): string
    {
        return $this->withRaisedMemoryLimit(function () use ($file, $directory): string {
            $basename = Str::uuid()->toString();
            $path = trim($directory, '/').'/'.$basename.'.jpg';

            [$image, $orientation] = $this->loadUploaded($file);
            $this->writeVariants($image, $path, $orientation);

            return $path;
        });
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

    /** exists() is a network call on S3 disks; the VPS uses the public disk. */
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
        return $this->withRaisedMemoryLimit(function () use ($path): bool {
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
        });
    }

    /**
     * @return array{0: \GdImage, 1: int}
     */
    private function loadUploaded(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $path = is_string($realPath) && is_file($realPath) ? $realPath : null;

        if ($path !== null) {
            $this->rejectOversizedPhoto($path);
            $orientation = $this->readOrientation($path);
            $contents = (string) file_get_contents($path);
        } else {
            $contents = (string) $file->getContent();
            $this->rejectOversizedPhotoFromString($contents);
            $orientation = 1;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new \RuntimeException('The uploaded image could not be read.');
        }

        return [$image, $orientation];
    }

    private function writeVariants(\GdImage $image, string $path, int $orientation): void
    {
        $fitted = $this->fit($image, self::MAX_LONG_SIDE);
        $oriented = $this->orient($fitted, $orientation);
        $thumb = $this->fit($oriented, self::THUMB_LONG_SIDE);
        $this->writeJpeg($path, $oriented);
        $this->writeJpeg($this->thumbnailPath($path), $thumb);

        $seen = [];

        foreach ([$thumb, $oriented, $fitted, $image] as $resource) {
            $id = spl_object_id($resource);

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            imagedestroy($resource);
        }
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

        return $this->copyOntoWhite($image, $targetWidth, $targetHeight);
    }

    private function orient(\GdImage $image, int $orientation): \GdImage
    {
        $working = $image;
        $background = imagecolorallocate($working, 255, 255, 255);
        $angle = match ($orientation) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => null,
        };

        if ($angle !== null && $background !== false) {
            $rotated = imagerotate($working, $angle, $background);

            if ($rotated !== false) {
                $working = $rotated;
            }
        }

        $flip = match ($orientation) {
            2, 5, 7 => IMG_FLIP_HORIZONTAL,
            4 => IMG_FLIP_VERTICAL,
            default => null,
        };

        if ($flip !== null) {
            imageflip($working, $flip);
        }

        return $working;
    }

    private function writeJpeg(string $path, \GdImage $image): void
    {
        $flat = $this->flattenOntoWhite($image);

        ob_start();
        imagejpeg($flat, null, self::JPEG_QUALITY);
        $bytes = (string) ob_get_clean();

        if ($flat !== $image) {
            imagedestroy($flat);
        }

        ListingStorage::disk()->put($path, $bytes);
    }

    private function flattenOntoWhite(\GdImage $image): \GdImage
    {
        return $this->copyOntoWhite($image, imagesx($image), imagesy($image));
    }

    private function copyOntoWhite(\GdImage $image, int $targetWidth, int $targetHeight): \GdImage
    {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($image), imagesy($image));

        return $canvas;
    }

    private function rejectOversizedPhoto(string $path): void
    {
        $info = @getimagesize($path);

        if (! is_array($info)) {
            return;
        }

        $this->rejectIfPixelCountExceeds((int) $info[0], (int) $info[1]);
    }

    private function rejectOversizedPhotoFromString(string $contents): void
    {
        $info = @getimagesizefromstring($contents);

        if (! is_array($info)) {
            return;
        }

        $this->rejectIfPixelCountExceeds((int) $info[0], (int) $info[1]);
    }

    private function rejectIfPixelCountExceeds(int $width, int $height): void
    {
        if ($width < 1 || $height < 1) {
            return;
        }

        if ($width * $height <= self::MAX_PIXELS) {
            return;
        }

        throw ValidationException::withMessages([
            'image' => 'Photo is too large; please use a smaller image.',
        ]);
    }

    private function readOrientation(string $path): int
    {
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);

            if (is_array($exif) && isset($exif['Orientation'])) {
                return (int) $exif['Orientation'];
            }
        }

        return $this->readJpegOrientation($path);
    }

    private function readJpegOrientation(string $path): int
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 1;
        }

        try {
            if (fread($handle, 2) !== "\xFF\xD8") {
                return 1;
            }

            while (! feof($handle)) {
                $marker = fread($handle, 2);

                if ($marker === false || strlen($marker) < 2 || $marker[0] !== "\xFF") {
                    return 1;
                }

                if ($marker === "\xFF\xDA" || $marker === "\xFF\xD9") {
                    return 1;
                }

                $lengthBytes = fread($handle, 2);

                if ($lengthBytes === false || strlen($lengthBytes) < 2) {
                    return 1;
                }

                $length = unpack('n', $lengthBytes)[1];
                $payloadLength = $length - 2;

                if ($payloadLength < 0) {
                    return 1;
                }

                $payload = $payloadLength > 0 ? fread($handle, $payloadLength) : '';

                if ($payload === false || strlen((string) $payload) < $payloadLength) {
                    return 1;
                }

                if ($marker === "\xFF\xE1" && str_starts_with((string) $payload, "Exif\x00\x00")) {
                    return $this->orientationFromExifTiff(substr((string) $payload, 6));
                }
            }
        } finally {
            fclose($handle);
        }

        return 1;
    }

    private function orientationFromExifTiff(string $tiff): int
    {
        if (strlen($tiff) < 8) {
            return 1;
        }

        $endian = substr($tiff, 0, 2);
        $short = match ($endian) {
            'MM' => 'n',
            'II' => 'v',
            default => null,
        };

        if ($short === null) {
            return 1;
        }

        $long = $endian === 'MM' ? 'N' : 'V';
        $ifdOffset = unpack($long, substr($tiff, 4, 4))[1];

        if ($ifdOffset + 2 > strlen($tiff)) {
            return 1;
        }

        $count = unpack($short, substr($tiff, $ifdOffset, 2))[1];
        $entryStart = $ifdOffset + 2;

        for ($i = 0; $i < $count; $i++) {
            $offset = $entryStart + ($i * 12);

            if ($offset + 12 > strlen($tiff)) {
                return 1;
            }

            $tag = unpack($short, substr($tiff, $offset, 2))[1];

            if ($tag !== 0x0112) {
                continue;
            }

            $type = unpack($short, substr($tiff, $offset + 2, 2))[1];
            $valueCount = unpack($long, substr($tiff, $offset + 4, 4))[1];

            if ($type === 3 && $valueCount >= 1) {
                return (int) unpack($short, substr($tiff, $offset + 8, 2))[1];
            }

            return (int) unpack($long, substr($tiff, $offset + 8, 4))[1];
        }

        return 1;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withRaisedMemoryLimit(callable $callback): mixed
    {
        $previous = ini_get('memory_limit');

        if ($this->shouldRaiseMemoryLimit($previous)) {
            ini_set('memory_limit', '256M');
        }

        try {
            return $callback();
        } finally {
            $this->restoreMemoryLimit($previous);
        }
    }

    private function shouldRaiseMemoryLimit(string|false $current): bool
    {
        if (! is_string($current) || $current === '-1') {
            return false;
        }

        return $this->memoryLimitToBytes($current) < 256 * 1024 * 1024;
    }

    private function restoreMemoryLimit(string|false $previous): void
    {
        if (! is_string($previous)) {
            return;
        }

        if (ini_set('memory_limit', $previous) === false) {
            Log::debug('Could not restore memory_limit to '.$previous.'.');
        }
    }

    private function memoryLimitToBytes(string $limit): ?int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
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
