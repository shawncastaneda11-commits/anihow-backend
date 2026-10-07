<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;

class StoreUserAvatarAction
{
    public function __construct(private ImageVariants $images) {}

    public function handle(User $user, UploadedFile $file): User
    {
        $user->avatar_path = $this->images->store($file, 'avatars');
        $user->save();

        return $user;
    }
}
