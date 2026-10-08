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

    public const LOOKUP_FAILED = "Couldn't look up this spot. You can still save the pin.";

    /**
     * @return array{places: list<array{label: string, latitude: float, longitude: float}>, message: ?string}
     */
    public function search(string $query, int $userId): array
    {
        $query = trim($query);

        if ($query === '') {
            return ['places' => [], 'message' => null];
        }

        $cacheKey = 'nominatim-search:'.hash('sha256', mb_strtolower($query));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return ['places' => $this->places($cached), 'message' => null];
        }

        if ($this->isBusy($userId)) {
            return ['places' => [], 'message' => self::BUSY_MESSAGE];
        }

        $this->consumeLimit($userId);

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
        Cache::put($cacheKey, $payload, now()->addDay());

        return ['places' => $this->places($payload), 'message' => null];
    }

    /**
     * @return array{barangay: ?string, municipality: ?string, province: ?string, address: ?string, label: ?string, message: ?string}
     */
    public function reverse(float $latitude, float $longitude, int $userId): array
    {
        $cacheKey = $this->reverseCacheKey($latitude, $longitude);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $this->reverseResult($cached);
        }

        if ($this->isBusy($userId)) {
            return $this->lookupFailed();
        }

        $this->consumeLimit($userId);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'zoom' => 18,
                'addressdetails' => 1,
            ]);
        } catch (Throwable) {
            return $this->lookupFailed();
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return $this->lookupFailed();
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();
        $mapped = $this->mapReverse($payload);

        if ($mapped['message'] !== null) {
            return $mapped;
        }

        Cache::put($cacheKey, $mapped, now()->addDays(30));

        return $mapped;
    }

    public function reverseCacheKey(float $latitude, float $longitude): string
    {
        return 'nominatim-reverse:'
            .number_format($latitude, 4, '.', '')
            .','
            .number_format($longitude, 4, '.', '');
    }

    private function isBusy(int $userId): bool
    {
        return RateLimiter::tooManyAttempts('nominatim-search', 1)
            || RateLimiter::tooManyAttempts('nominatim-search-user-'.$userId, 30);
    }

    private function consumeLimit(int $userId): void
    {
        RateLimiter::hit('nominatim-search', 1);
        RateLimiter::hit('nominatim-search-user-'.$userId, 60);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{barangay: ?string, municipality: ?string, province: ?string, address: ?string, label: ?string, message: ?string}
     */
    private function mapReverse(array $payload): array
    {
        $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
        $barangay = $this->firstFilled($address, ['quarter', 'village', 'suburb', 'neighbourhood', 'hamlet']);
        $municipality = $this->firstFilled($address, ['town', 'city', 'municipality']);
        $province = $this->firstFilled($address, ['state', 'province']);
        $road = $this->firstFilled($address, ['road']);
        $line = $this->joinUnique([$road, $barangay, $municipality]);
        $label = $this->joinUnique([$barangay, $municipality, $province]);

        if ($line === '' && $label === '') {
            return $this->lookupFailed();
        }

        return [
            'barangay' => $barangay,
            'municipality' => $municipality,
            'province' => $province,
            'address' => $line !== '' ? $line : null,
            'label' => $label !== '' ? '📍 '.$label : null,
            'message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array{barangay: ?string, municipality: ?string, province: ?string, address: ?string, label: ?string, message: ?string}
     */
    private function reverseResult(array $cached): array
    {
        return [
            'barangay' => $this->nullableString($cached['barangay'] ?? null),
            'municipality' => $this->nullableString($cached['municipality'] ?? null),
            'province' => $this->nullableString($cached['province'] ?? null),
            'address' => $this->nullableString($cached['address'] ?? null),
            'label' => $this->nullableString($cached['label'] ?? null),
            'message' => null,
        ];
    }

    /**
     * @return array{barangay: null, municipality: null, province: null, address: null, label: null, message: string}
     */
    private function lookupFailed(): array
    {
        return [
            'barangay' => null,
            'municipality' => null,
            'province' => null,
            'address' => null,
            'label' => null,
            'message' => self::LOOKUP_FAILED,
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private function firstFilled(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->nullableString($address[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  list<?string>  $parts
     */
    private function joinUnique(array $parts): string
    {
        $kept = [];

        foreach ($parts as $part) {
            if ($part === null || $part === '') {
                continue;
            }

            $duplicate = false;

            foreach ($kept as $existing) {
                if (mb_strtolower($existing) === mb_strtolower($part)) {
                    $duplicate = true;

                    break;
                }
            }

            if (! $duplicate) {
                $kept[] = $part;
            }
        }

        return implode(', ', $kept);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
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
