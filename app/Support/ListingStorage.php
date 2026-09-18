<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ListingStorage
{
    public static function diskName(): string
    {
        return (string) config('anihow.listing_disk', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }
}
