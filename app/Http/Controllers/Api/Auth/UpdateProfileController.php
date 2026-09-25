<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Privacy\UpdateOwnProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\UserResource;

class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, UpdateOwnProfileAction $updateProfile): UserResource
    {
        $user = $updateProfile->handle($request->user(), $request->validated());

        return new UserResource($user->load(['roles', 'farm']));
    }
}
