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
        return $user->can(Permission::ManageOwnTawadRules->value)
            || $user->can(Permission::ManageFarmListings->value);
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
        if ($user->can(Permission::ManageOwnTawadRules->value) && $rule->listing->isOwnedBy($user)) {
            return true;
        }

        // The farm's Content Editor manages the farm's listings, tawad included.
        return $user->can(Permission::ManageFarmListings->value)
            && $user->farm_id !== null
            && (int) $user->farm_id === (int) $rule->listing->farm_id;
    }
}
