<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExportLog;
use App\Models\User;

class ExportLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GenerateExports->value);
    }

    public function view(User $user, ExportLog $exportLog): bool
    {
        return $user->can(Permission::GenerateExports->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ExportLog $exportLog): bool
    {
        return false;
    }

    public function delete(User $user, ExportLog $exportLog): bool
    {
        return false;
    }
}
