<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\RegisterBuyerAction;
use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterBuyerRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterBuyerRequest $request, RegisterBuyerAction $registerBuyer): JsonResponse
    {
        $result = $registerBuyer->handle($request->validated());
        $user = $result['user'];

        if (SendEmailVerificationCodeAction::mailRequiredButMissing()) {
            SendEmailVerificationCodeAction::logUnavailable();

            return response()->json([
                'message' => SendEmailVerificationCodeAction::unavailableMessage(),
            ], 503);
        }

        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        $extra = [
            'token' => $token,
            'token_type' => 'Bearer',
        ];

        if (SendEmailVerificationCodeAction::shouldExposeCode()) {
            $extra['verification_code'] = $result['verification_code'];
        }

        return (new UserResource($user))
            ->additional($extra)
            ->response()
            ->setStatusCode(201);
    }
}
