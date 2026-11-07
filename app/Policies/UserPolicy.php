<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;

/**
 * COSEL manages people's accounts; ICTD manages administrator accounts.
 * See AccountAuthority.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return AccountAuthority::managesAny($user)
            || $user->can(Permission::ViewOwnFarmRoster->value);
    }

    public function view(User $user, User $model): bool
    {
        if (AccountAuthority::canManage($user, $model)) {
            return true;
        }

        if ($user->id === $model->id) {
            return true;
        }

        return $this->sharesFarm($user, $model)
            && $user->can(Permission::ViewOwnFarmRoster->value);
    }

    public function create(User $user): bool
    {
        return AccountAuthority::managesAny($user);
    }

    /**
     * A Content Editor's roster view is read-only. Approvals stay with COSEL,
     * which is the line that keeps the role out of account power.
     */
    public function update(User $user, User $model): bool
    {
        return AccountAuthority::canManage($user, $model) || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return AccountAuthority::canManageOther($user, $model);
    }

    public function suspend(User $user, User $model): bool
    {
        if (! AccountAuthority::canManageOther($user, $model)) {
            return false;
        }

        // Administrators are suspended by whoever manages administrators.
        return $model->isAdministrator() || $user->can(Permission::SuspendAccounts->value);
    }

    private function sharesFarm(User $user, User $model): bool
    {
        return $user->farm_id !== null && $user->farm_id === $model->farm_id;
    }
}
