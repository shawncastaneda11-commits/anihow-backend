<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\SendPasswordResetCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, SendPasswordResetCodeAction $codes): JsonResponse
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

        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();
        $codes->forget($user);

        event(new PasswordReset($user));

        return response()->json([
            'message' => 'Password reset. Sign in with your new password.',
        ]);
    }
}
