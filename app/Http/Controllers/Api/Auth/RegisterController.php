<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\RegisterBuyerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterBuyerRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterBuyerRequest $request, RegisterBuyerAction $registerBuyer): JsonResponse
    {
        $user = $registerBuyer->handle($request->validated());
        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        return (new UserResource($user))
            ->additional([
                'token' => $token,
                'token_type' => 'Bearer',
            ])
            ->response()
            ->setStatusCode(201);
    }
}
