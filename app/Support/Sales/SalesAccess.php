<?php

namespace App\Support\Sales;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees orders, reservations and payments in the CMS.
 *
 * COSEL sees every farm's, in full. A Content Editor sees their own farm's,
 * read-only, with buyers by first name and without payment screenshots,
 * reference numbers or order chats: those carry people's details.
 */
final class SalesAccess
{
    public static function seesAll(?User $user): bool
    {
        return $user?->can(Permission::ViewAllOrders->value) ?? false;
    }

    /** The farm a Content Editor may see, or null when they see none or all. */
    public static function ownFarmId(?User $user): ?int
    {
        if ($user === null || self::seesAll($user) || ! $user->can(Permission::ViewOwnFarmOrders->value)) {
            return null;
        }

        return $user->farm_id !== null ? (int) $user->farm_id : null;
    }

    public static function canOpen(?User $user): bool
    {
        return self::seesAll($user) || self::ownFarmId($user) !== null;
    }

    /**
     * Narrows a query to what this person may see. $farmColumn is the farm
     * column of the query's own table.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, ?User $user, string $farmColumn): Builder
    {
        if (self::seesAll($user)) {
            return $query;
        }

        $farmId = self::ownFarmId($user);

        return $farmId === null ? $query->whereRaw('1 = 0') : $query->where($farmColumn, $farmId);
    }

    /** The buyer's name as this person may see it: in full for COSEL, first name otherwise. */
    public static function buyerName(?string $name, ?User $viewer): ?string
    {
        if ($name === null || self::seesAll($viewer)) {
            return $name;
        }

        $first = strtok(trim($name), ' ');

        return $first === false ? $name : $first;
    }
}
