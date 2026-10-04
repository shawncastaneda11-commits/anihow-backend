<?php

namespace App\Actions\Shop;

use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Http\UploadedFile;

class StoreShopCoverAction
{
    public function __construct(private ImageVariants $images) {}

    public function handle(User $user, UploadedFile $file): User
    {
        $user->cover_photo_path = $this->images->store($file, 'shop-covers');
        $user->save();

        return $user;
    }
}
