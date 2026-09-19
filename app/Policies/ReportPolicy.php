<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ResolveReports->value);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->can(Permission::ResolveReports->value)
            || $report->reporter_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SubmitReports->value);
    }

    public function resolve(User $user, Report $report): bool
    {
        return $user->can(Permission::ResolveReports->value);
    }
}
