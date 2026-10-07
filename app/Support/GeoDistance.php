<?php

namespace App\Support;

use Illuminate\Http\Request;

class GeoDistance
{
    /**
     * The buyer's coordinates stay on the query string. This only reads them.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function point(Request $request): ?array
    {
        $lat = $request->query('near_lat');
        $lng = $request->query('near_lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $latitude = (float) $lat;
        $longitude = (float) $lng;

        if ($latitude < 4 || $latitude > 22 || $longitude < 116 || $longitude > 127) {
            return null;
        }

        return ['lat' => $latitude, 'lng' => $longitude];
    }

    public static function requested(Request $request): bool
    {
        return self::point($request) !== null;
    }

    public static function kilometers(?float $latitude, ?float $longitude, Request $request): ?float
    {
        $point = self::point($request);

        if ($point === null || $latitude === null || $longitude === null) {
            return null;
        }

        $earthKm = 6371.0;
        $latFrom = deg2rad($point['lat']);
        $latTo = deg2rad($latitude);
        $latDelta = deg2rad($latitude - $point['lat']);
        $lngDelta = deg2rad($longitude - $point['lng']);
        $haversine = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $arc = 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));

        return round($earthKm * $arc, 1);
    }
}
