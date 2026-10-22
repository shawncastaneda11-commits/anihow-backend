<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\SendPasswordResetCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyResetCodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Checks the reset code before the app asks for a new password. The code
 * stays valid; reset-password checks it again and then forgets it. A wrong
 * guess counts toward the same lockout as a wrong reset.
 */
class VerifyResetCodeController extends Controller
{
    public function __invoke(VerifyResetCodeRequest $request, SendPasswordResetCodeAction $codes): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (
            ! $user instanceof User
            || ! $user->status->canAuthenticate()
            || ! $codes->matches($user, $request->validated('code'))
        ) {
            throw ValidationException::withMessages([
                'code' => ['The reset code is invalid or has expired.'],
            ]);
        }

        return response()->json(['message' => 'Code accepted. Choose a new password.']);
    }
}
