<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TawadRule;
use App\Models\User;

class TawadRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOwnTawadRules->value);
    }

    public function view(User $user, TawadRule $rule): bool
    {
        return $this->owns($user, $rule);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnTawadRules->value);
    }

    public function update(User $user, TawadRule $rule): bool
    {
        return $this->owns($user, $rule);
    }

    public function delete(User $user, TawadRule $rule): bool
    {
        return $this->owns($user, $rule);
    }

    private function owns(User $user, TawadRule $rule): bool
    {
        return $user->can(Permission::ManageOwnTawadRules->value)
            && $rule->listing->isOwnedBy($user);
    }
}
