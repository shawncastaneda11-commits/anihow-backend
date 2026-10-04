<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FarmProximity
{
    /**
     * Equirectangular sort: (dLat)^2 + (dLng * cos(nearLat))^2.
     * Farms without a pin come last. Without a buyer point, municipality then name.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function apply(Builder $query, string $farmForeignKey, ?float $latitude, ?float $longitude): Builder
    {
        $table = $query->getModel()->getTable();

        $query->leftJoin('farms as pin_farms', 'pin_farms.id', '=', $farmForeignKey)
            ->select($table.'.*');

        if ($latitude === null || $longitude === null) {
            return $query
                ->orderByRaw("case when pin_farms.municipality is null or pin_farms.municipality = '' then 1 else 0 end")
                ->orderBy('pin_farms.municipality')
                ->orderBy('pin_farms.name')
                ->orderBy($table.'.id');
        }

        $cosLat = cos(deg2rad($latitude));

        return $query
            ->orderByRaw('case when pin_farms.latitude is null or pin_farms.longitude is null then 1 else 0 end')
            ->orderByRaw(
                '(pin_farms.latitude - ?) * (pin_farms.latitude - ?) + ((pin_farms.longitude - ?) * ?) * ((pin_farms.longitude - ?) * ?)',
                [$latitude, $latitude, $longitude, $cosLat, $longitude, $cosLat],
            )
            ->orderBy($table.'.id');
    }
}
