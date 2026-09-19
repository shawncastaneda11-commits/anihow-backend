<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageAccounts->value)
            || $user->can(Permission::ViewOwnFarmRoster->value);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->can(Permission::ManageAccounts->value)) {
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
        return $user->can(Permission::ManageAccounts->value);
    }

    /**
     * A Content Editor's roster view is read-only. Approvals stay with the
     * Super Admin, which is the line that keeps the role out of account power.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::ManageAccounts->value) || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::ManageAccounts->value) && $user->id !== $model->id;
    }

    public function approve(User $user, User $model): bool
    {
        return $user->can(Permission::ApproveFarmerSeller->value);
    }

    public function suspend(User $user, User $model): bool
    {
        return $user->can(Permission::SuspendAccounts->value) && $user->id !== $model->id;
    }

    private function sharesFarm(User $user, User $model): bool
    {
        return $user->farm_id !== null && $user->farm_id === $model->farm_id;
    }
}
