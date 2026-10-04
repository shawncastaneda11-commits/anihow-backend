<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Profile\DeleteUserAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;

class DeleteUserAvatarController extends Controller
{
    public function __invoke(Request $request, DeleteUserAvatarAction $deleteAvatar): UserResource
    {
        abort_unless($request->user() !== null, 401);

        $user = $deleteAvatar->handle($request->user());

        return new UserResource($user->load(['roles', 'farm']));
    }
}
