<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Actions\Auth\SendPasswordResetCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request, SendPasswordResetCodeAction $sendCode): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Says plainly when no account uses the email, so a mistyped address
        // is not left waiting for a code. throttle:auth limits the guessing.
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'email' => 'No AniHow account uses this email. Check the spelling.',
            ]);
        }

        if (! $user->status->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => 'This account is suspended. Contact the AniHow administrator.',
            ]);
        }

        if (SendEmailVerificationCodeAction::mailRequiredButMissing()) {
            SendEmailVerificationCodeAction::logUnavailable();
        } else {
            $sendCode->handle($user);
        }

        return response()->json([
            'message' => 'We sent a 6-digit code to your email.',
        ]);
    }
}
