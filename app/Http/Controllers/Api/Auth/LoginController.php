<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LoginUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUserAction $loginUser): JsonResponse
    {
        $result = $loginUser->handle(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name') ?? 'mobile',
        );

        return (new UserResource($result['user']))
            ->additional([
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ])
            ->response();
    }
}
