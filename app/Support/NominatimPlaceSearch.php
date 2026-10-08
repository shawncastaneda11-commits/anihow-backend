<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class NominatimPlaceSearch
{
    public const USER_AGENT = 'AniHow/1.0 (anihow.marketplace@gmail.com)';

    public const BUSY_MESSAGE = 'Search is busy, try again in a moment.';

    /**
     * @return array{places: list<array{label: string, latitude: float, longitude: float}>, message: ?string}
     */
    public function search(string $query, int $userId): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['places' => [], 'message' => null];
        }

        $globalKey = 'nominatim-search';
        $userKey = 'nominatim-search-user-'.$userId;

        if (RateLimiter::tooManyAttempts($globalKey, 1) || RateLimiter::tooManyAttempts($userKey, 30)) {
            return ['places' => [], 'message' => self::BUSY_MESSAGE];
        }

        RateLimiter::hit($globalKey, 1);
        RateLimiter::hit($userKey, 60);

        $cacheKey = 'nominatim-search:'.hash('sha256', mb_strtolower($query));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return ['places' => $this->places($cached), 'message' => null];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'jsonv2',
                'countrycodes' => 'ph',
                'limit' => 5,
                'q' => $query,
            ]);
        } catch (Throwable) {
            return ['places' => [], 'message' => null];
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return ['places' => [], 'message' => null];
        }

        /** @var list<mixed> $payload */
        $payload = $response->json();
        $places = $this->places($payload);
        Cache::put($cacheKey, $payload, now()->addDay());

        return ['places' => $places, 'message' => null];
    }

    /**
     * @param  list<mixed>  $payload
     * @return list<array{label: string, latitude: float, longitude: float}>
     */
    private function places(array $payload): array
    {
        $places = [];

        foreach ($payload as $row) {
            if (! is_array($row)) {
                continue;
            }

            $latitude = $row['lat'] ?? null;
            $longitude = $row['lon'] ?? null;

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            $label = $row['display_name'] ?? null;

            $places[] = [
                'label' => is_string($label) && $label !== '' ? $label : 'Place',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];

            if (count($places) === 5) {
                break;
            }
        }

        return $places;
    }
}
