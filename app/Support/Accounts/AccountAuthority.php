<?php

namespace App\Support\Accounts;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may manage which accounts.
 *
 * COSEL's Account Manager holds ManageAccounts: farmer-seller, buyer, and
 * Content Editor accounts. The ICTD Super Admin holds ManageAdminAccounts:
 * administrator accounts (Super Admin and Account Manager) and nothing else.
 * Neither can create the other's kind of account, so COSEL never makes an
 * administrator and ICTD never handles people's accounts or ID images.
 * Nobody suspends, deletes, or changes the role of their own account.
 */
final class AccountAuthority
{
    public static function managesPeople(?User $actor): bool
    {
        return $actor?->can(Permission::ManageAccounts->value) ?? false;
    }

    public static function managesAdmins(?User $actor): bool
    {
        return $actor?->can(Permission::ManageAdminAccounts->value) ?? false;
    }

    public static function managesAny(?User $actor): bool
    {
        return self::managesPeople($actor) || self::managesAdmins($actor);
    }

    public static function isAdminAccount(User $target): bool
    {
        return $target->isAdministrator();
    }

    /** May change this account's role, farm, status, or password. */
    public static function canManage(?User $actor, User $target): bool
    {
        if ($actor === null) {
            return false;
        }

        return self::isAdminAccount($target) ? self::managesAdmins($actor) : self::managesPeople($actor);
    }

    /** As canManage(), but never on one's own account. */
    public static function canManageOther(?User $actor, User $target): bool
    {
        return $actor !== null && $actor->isNot($target) && self::canManage($actor, $target);
    }

    /**
     * Roles this person may give an account.
     *
     * @return list<Role>
     */
    public static function assignableRoles(?User $actor): array
    {
        $roles = [];

        if (self::managesPeople($actor)) {
            array_push($roles, Role::ContentEditor, Role::FarmerSeller, Role::Buyer);
        }

        if (self::managesAdmins($actor)) {
            array_push($roles, Role::AccountManager, Role::SuperAdmin);
        }

        return $roles;
    }

    /**
     * The accounts this person sees in the account list.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function scope(Builder $query, User $actor): Builder
    {
        $admin = array_column(Role::adminRoles(), 'value');
        $people = self::managesPeople($actor);
        $admins = self::managesAdmins($actor);

        if ($people && $admins) {
            return $query;
        }

        // Everyone always sees their own account.
        if ($admins) {
            return $query->where(fn (Builder $q): Builder => $q
                ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', $admin))
                ->orWhereKey($actor->getKey()));
        }

        if ($people) {
            return $query->where(fn (Builder $q): Builder => $q
                ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn('name', $admin))
                ->orWhereKey($actor->getKey()));
        }

        return $query->where('farm_id', $actor->farm_id);
    }
}
