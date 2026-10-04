<?php

namespace App\Actions\Shop;

use App\Models\User;

class DeleteShopCoverAction
{
    public function handle(User $user): User
    {
        $user->cover_photo_path = null;
        $user->save();

        return $user;
    }
}
