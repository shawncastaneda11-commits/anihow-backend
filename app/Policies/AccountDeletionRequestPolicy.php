<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AccountDeletionRequest;
use App\Models\User;

class AccountDeletionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageAccounts->value);
    }

    public function view(User $user, AccountDeletionRequest $accountDeletionRequest): bool
    {
        return $user->can(Permission::ManageAccounts->value)
            || $accountDeletionRequest->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RequestAccountDeletion->value);
    }

    public function delete(User $user, AccountDeletionRequest $accountDeletionRequest): bool
    {
        return $user->can(Permission::RequestAccountDeletion->value)
            && $accountDeletionRequest->user_id === $user->id
            && $accountDeletionRequest->isPending();
    }

    public function process(User $user, AccountDeletionRequest $accountDeletionRequest): bool
    {
        return $user->can(Permission::ManageAccounts->value)
            && $accountDeletionRequest->isPending();
    }
}
