<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Profile\StoreUserAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\StoreUserAvatarRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\UploadedFile;

class StoreUserAvatarController extends Controller
{
    public function __invoke(StoreUserAvatarRequest $request, StoreUserAvatarAction $storeAvatar): UserResource
    {
        $image = $request->file('image');
        abort_unless($image instanceof UploadedFile, 422);

        $user = $storeAvatar->handle($request->user(), $image);

        return new UserResource($user->load(['roles', 'farm']));
    }
}
