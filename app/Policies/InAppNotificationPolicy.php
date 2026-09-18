<?php

namespace App\Policies;

use App\Models\InAppNotification;
use App\Models\User;

class InAppNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InAppNotification $notification): bool
    {
        return $notification->isOwnedBy($user);
    }

    public function update(User $user, InAppNotification $notification): bool
    {
        return $notification->isOwnedBy($user);
    }
}
