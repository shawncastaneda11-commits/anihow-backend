<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Actions\Auth\SendPasswordResetCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, SendPasswordResetCodeAction $sendCode): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user instanceof User && $user->status->canAuthenticate()) {
            if (SendEmailVerificationCodeAction::mailRequiredButMissing()) {
                SendEmailVerificationCodeAction::logUnavailable();
            } else {
                $sendCode->handle($user);
            }
        }

        return response()->json([
            'message' => 'If that email is registered, a password reset link was sent.',
        ]);
    }
}
