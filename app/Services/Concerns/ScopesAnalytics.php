<?php

namespace App\Services\Concerns;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * System-wide, farm-scoped, or own, decided by what the viewer holds.
 */
trait ScopesAnalytics
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeAnalytics(Builder $query, ?User $viewer, string $table): Builder
    {
        if ($viewer === null || $viewer->can(Permission::ViewSystemAnalytics->value)) {
            return $query;
        }

        if ($viewer->can(Permission::ViewFarmAnalytics->value) && $viewer->farm_id !== null) {
            return $query->where($table.'.farm_id', $viewer->farm_id);
        }

        return $query->where($table.'.farmer_seller_id', $viewer->id);
    }
}
