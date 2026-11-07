<?php

namespace App\Support;

/**
 * App version strings such as "1.0.3" (a build suffix like "+4" is ignored).
 */
final class AppVersion
{
    public static function normalize(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = trim(explode('+', $version)[0]);

        return preg_match('/^\d+(\.\d+){0,3}$/', $version) === 1 ? $version : null;
    }

    /** True when $version is older than $minimum. Unknown versions are never older. */
    public static function isOlder(?string $version, ?string $minimum): bool
    {
        $version = self::normalize($version);
        $minimum = self::normalize($minimum);

        if ($version === null || $minimum === null) {
            return false;
        }

        return version_compare($version, $minimum, '<');
    }
}
