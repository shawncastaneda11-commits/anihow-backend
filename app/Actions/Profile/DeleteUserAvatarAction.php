<?php

namespace App\Actions\Profile;

use App\Models\User;

class DeleteUserAvatarAction
{
    public function handle(User $user): User
    {
        $user->avatar_path = null;
        $user->save();

        return $user;
    }
}
