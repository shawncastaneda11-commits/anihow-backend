<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleMapsLinkResolver
{
    public const UNREADABLE = "Couldn't read a location from this link. Try searching or drop the pin on the map.";

    /**
     * @var list<string>
     */
    private const SHORT_HOSTS = [
        'maps.app.goo.gl',
        'goo.gl',
        'maps.google.com',
    ];

    /**
     * @var list<string>
     */
    private const MAPS_PATH_HOSTS = [
        'www.google.com',
        'google.com',
    ];

    /**
     * @return array{latitude: ?float, longitude: ?float, message: ?string}
     */
    public function resolve(string $url): array
    {
        $current = trim($url);
        $failure = [
            'latitude' => null,
            'longitude' => null,
            'message' => self::UNREADABLE,
        ];

        for ($hop = 0; $hop < 3; $hop++) {
            if (! $this->allowed($current)) {
                return $failure;
            }

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                ])->timeout(5)->withHeaders([
                    'User-Agent' => NominatimPlaceSearch::USER_AGENT,
                ])->get($current);
            } catch (Throwable) {
                return $failure;
            }

            if ($this->isRedirect($response)) {
                if ($hop === 2) {
                    return $failure;
                }

                $location = $response->header('Location');

                if (is_array($location)) {
                    $location = $location[0] ?? null;
                }

                if (! is_string($location) || trim($location) === '') {
                    return $failure;
                }

                $next = $this->absolute($current, trim($location));

                if (! $this->allowed($next)) {
                    return $failure;
                }

                $current = $next;

                continue;
            }

            return $this->coordinates($current)
                ?? $this->coordinates((string) $response->body())
                ?? $failure;
        }

        return $failure;
    }

    private function isRedirect(Response $response): bool
    {
        return in_array($response->status(), [301, 302, 303, 307, 308], true);
    }

    private function allowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (in_array($host, self::SHORT_HOSTS, true)) {
            return true;
        }

        if (! in_array($host, self::MAPS_PATH_HOSTS, true)) {
            return false;
        }

        return str_starts_with($parts['path'] ?? '/', '/maps');
    }

    private function absolute(string $current, string $location): string
    {
        if (str_starts_with($location, 'https://') || str_starts_with($location, 'http://')) {
            return $location;
        }

        $parts = parse_url($current);
        $origin = strtolower((string) ($parts['scheme'] ?? 'https')).'://'.strtolower((string) ($parts['host'] ?? ''));

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.'/'.$location;
    }

    /**
     * @return array{latitude: float, longitude: float, message: null}|null
     */
    private function coordinates(string $value): ?array
    {
        $value = urldecode($value);

        $patterns = [
            '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
            '/[?&#]q=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
            '/[?&#]ll=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $value, $matches)) {
                continue;
            }

            return [
                'latitude' => (float) $matches[1],
                'longitude' => (float) $matches[2],
                'message' => null,
            ];
        }

        return null;
    }
}
