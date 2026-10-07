<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class FarmProximity
{
    /**
     * Equirectangular sort: (dLat)^2 + (dLng * cos(nearLat))^2.
     * Farms without a pin come last. Without a buyer point, municipality then name.
     *
     * Correlated subqueries only. A join plus select() would drop withCount columns.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function apply(Builder $query, string $farmForeignKey, ?float $latitude, ?float $longitude): Builder
    {
        $key = self::qualifiedColumn($farmForeignKey);
        $table = $query->getModel()->getTable();

        if ($latitude === null || $longitude === null) {
            return $query
                ->orderByRaw("coalesce((select case when f.municipality is null or f.municipality = '' then 1 else 0 end from farms f where f.id = {$key}), 1)")
                ->orderByRaw("(select f.municipality from farms f where f.id = {$key})")
                ->orderByRaw("(select f.name from farms f where f.id = {$key})")
                ->orderBy($table.'.id');
        }

        $cosLat = cos(deg2rad($latitude));

        return $query
            ->orderByRaw("coalesce((select case when f.latitude is null or f.longitude is null then 1 else 0 end from farms f where f.id = {$key}), 1)")
            ->orderByRaw(
                '(select (f.latitude - ?) * (f.latitude - ?) + ((f.longitude - ?) * ?) * ((f.longitude - ?) * ?) from farms f where f.id = '.$key.')',
                [$latitude, $latitude, $longitude, $cosLat, $longitude, $cosLat],
            )
            ->orderBy($table.'.id');
    }

    /**
     * Callers pass a qualified column such as users.farm_id. Never request input.
     */
    private static function qualifiedColumn(string $column): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Farm foreign key must be a qualified column.');
        }

        return $column;
    }
}
